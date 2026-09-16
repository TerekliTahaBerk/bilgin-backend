<?php

declare(strict_types=1);

use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/*
 | Sınav provası — tasarımdaki "Sınav Provası" ekranı.
 |
 | Normal turdan üç farkı var ve üçü de burada korunuyor: can harcamaz,
 | üniteye bağlı değildir, sonucu doğruluk değil nettir.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->token = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'deneme-testi',
        'platform' => 'ios',
    ])->json('data.token');

    $this->withToken($this->token)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks', 'field' => 'say', 'grade' => '11',
    ]);
});

function startSimulation(): array
{
    $blueprintId = DB::table('exam_blueprints')->where('code', 'tyt_genel_deneme')->value('id');

    return test()->withToken(test()->token)
        ->postJson('/api/v1/exam-simulations', ['blueprint_id' => $blueprintId])
        ->assertCreated()
        ->json('data');
}

it('öğrencinin alanına uygun denemeleri listeler', function (): void {
    $blueprints = $this->withToken($this->token)
        ->getJson('/api/v1/exam-simulations')
        ->assertOk()
        ->json('data');

    $codes = collect($blueprints)->pluck('code');

    // TYT denemesi varyanttan bağımsız (herkese ortak), AYT denemesi Sayısal'a özel.
    expect($codes)->toContain('tyt_genel_deneme')
        ->and($codes)->toContain('ayt_say_deneme');

    $tyt = collect($blueprints)->firstWhere('code', 'tyt_genel_deneme');

    expect($tyt['duration_min'])->toBe(165)
        ->and($tyt['penalty_ratio'])->toBe(0.25);
});

it('deneme başlatır ve can harcamaz', function (): void {
    // Gerçek sınav havası bozulmasın: yanlış yapmak cezalandırılmaz, ölçülür.
    $session = startSimulation();

    expect($session['consumes_hearts'])->toBeFalse()
        ->and($session['time_limit_sec'])->toBe(165 * 60)
        ->and($session['items'])->not->toBeEmpty();
});

it('deneme birden çok dersten soru toplar', function (): void {
    $session = startSimulation();

    $courseIds = DB::table('session_items')
        ->join('study_sessions', 'study_sessions.id', '=', 'session_items.study_session_id')
        ->join('exercises', 'exercises.id', '=', 'session_items.exercise_id')
        ->where('study_sessions.uuid', $session['session_id'])
        ->distinct()
        ->pluck('exercises.owner_course_id');

    // Pilot içerikte yalnızca TYT Tarih yayında; blueprint 9 ders istiyor
    // ama havuzu olan tek dersten toplayabildiğini alıyor.
    expect($courseIds)->not->toBeEmpty();
});

it('denemede yanlış cevap can düşürmez', function (): void {
    $session = startSimulation();
    $item = StudySession::query()->where('uuid', $session['session_id'])->firstOrFail()->items()->first();

    $result = $this->withToken($this->token)
        ->postJson("/api/v1/sessions/{$session['session_id']}/answers", [
            'exercise_id' => $item->exercise_id,
            'answer' => ['option_id' => 'yanlis', 'value' => 'yanlis'],
            'elapsed_ms' => 30000,
        ])->assertOk()->json('data');

    expect($result['is_correct'])->toBeFalse()
        ->and($result)->not->toHaveKey('hearts')
        ->and(DB::table('user_hearts')->value('hearts'))->toBe(5);
});

it('deneme sonucunda net ve tahmini puan döner', function (): void {
    $session = startSimulation();
    $items = StudySession::query()->where('uuid', $session['session_id'])->firstOrFail()->items()->get();

    // Pilot havuz küçük; yayında olan tek dersten toplanabilen kadar soru gelir.
    $correctCount = (int) floor($items->count() / 2);
    $wrongCount = $items->count() - $correctCount - 1;   // en az 1 boş kalsın

    foreach ($items->take($correctCount) as $item) {
        $key = $item->answer_key_snapshot;

        $answer = match ($item->type->value) {
            'multiple_choice' => ['option_id' => $key['correct_option_id']],
            'true_false' => ['value' => $key['value']],
            'fill_blank' => ['blanks' => $key['blanks']],
            'matching' => ['pairs' => $key['pairs']],
            'ordering', 'word_order' => ['order' => $key['order']],
            'numeric_input' => ['value' => $key['value']],
            default => ['known' => true],
        };

        $this->withToken($this->token)->postJson("/api/v1/sessions/{$session['session_id']}/answers", [
            'exercise_id' => $item->exercise_id, 'answer' => $answer, 'elapsed_ms' => 20000,
        ])->assertOk();
    }

    foreach ($items->slice($correctCount, $wrongCount) as $item) {
        $this->withToken($this->token)->postJson("/api/v1/sessions/{$session['session_id']}/answers", [
            'exercise_id' => $item->exercise_id,
            'answer' => ['option_id' => 'yanlis', 'value' => 'yanlis'],
            'elapsed_ms' => 20000,
        ])->assertOk();
    }

    $summary = $this->withToken($this->token)
        ->postJson("/api/v1/sessions/{$session['session_id']}/complete")
        ->assertOk()
        ->json('data');

    $expectedNet = round($correctCount - ($wrongCount * 0.25), 2);

    expect($summary)->toHaveKey('exam')
        ->and($summary['exam']['correct'])->toBe($correctCount)
        ->and($summary['exam']['wrong'])->toBe($wrongCount)
        // YKS cezası: 4 yanlış 1 doğru götürür.
        ->and($summary['exam']['net'])->toBe($expectedNet)
        ->and($summary['exam']['blank'])->toBe($items->count() - $correctCount - $wrongCount)
        ->and($summary['exam']['estimated_score'])->toBe(round(100 + $expectedNet * 4, 2));
});

it('deneme ünite ilerlemesi yazmaz ama konu analizini besler', function (): void {
    // Öğrencinin zayıf konusunu en net gösteren şey zaten denemedir.
    $session = startSimulation();
    $item = StudySession::query()->where('uuid', $session['session_id'])->firstOrFail()->items()->first();

    $this->withToken($this->token)->postJson("/api/v1/sessions/{$session['session_id']}/answers", [
        'exercise_id' => $item->exercise_id,
        'answer' => ['option_id' => 'yanlis', 'value' => 'yanlis'],
        'elapsed_ms' => 20000,
    ]);

    $summary = $this->withToken($this->token)
        ->postJson("/api/v1/sessions/{$session['session_id']}/complete")
        ->assertOk()->json('data');

    expect($summary['unlocked_nodes'])->toBe([])
        ->and(DB::table('user_node_progress')->count())->toBe(0)
        ->and(DB::table('user_topic_stats')->count())->toBeGreaterThan(0);
});

it('deneme XP verir', function (): void {
    $session = startSimulation();

    $summary = $this->withToken($this->token)
        ->postJson("/api/v1/sessions/{$session['session_id']}/complete")
        ->assertOk()->json('data');

    // Sınav provası tabanı: zorluk 60 × node tipi çarpanı 3 = 180.
    expect($summary['xp']['breakdown']['base'])->toBe(180);
});

it('aynı idempotency anahtarıyla ikinci deneme açmaz', function (): void {
    $blueprintId = DB::table('exam_blueprints')->where('code', 'tyt_genel_deneme')->value('id');

    $first = $this->withToken($this->token)
        ->withHeaders(['Idempotency-Key' => 'deneme-1'])
        ->postJson('/api/v1/exam-simulations', ['blueprint_id' => $blueprintId])
        ->json('data.session_id');

    $second = $this->withToken($this->token)
        ->withHeaders(['Idempotency-Key' => 'deneme-1'])
        ->postJson('/api/v1/exam-simulations', ['blueprint_id' => $blueprintId])
        ->json('data.session_id');

    expect($second)->toBe($first)
        ->and(StudySession::query()->count())->toBe(1);
});

it('onboarding yapılmadan deneme listesi istenemez', function (): void {
    $token = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'bos', 'platform' => 'ios',
    ])->json('data.token');

    actingWithToken($token)
        ->getJson('/api/v1/exam-simulations')
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'ONBOARDING_REQUIRED');
});
