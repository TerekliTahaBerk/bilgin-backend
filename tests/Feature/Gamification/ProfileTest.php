<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/*
 | Profil ekranı: istatistikler, rozetler ve konu analizi.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->token = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'profil-testi', 'platform' => 'ios',
    ])->json('data.token');

    $this->withToken($this->token)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks', 'field' => 'say', 'grade' => '11',
    ]);
});

/** Bir turu hatasız bitirir ve sonuç özetini döner. */
function completeRound(int $nodeIndex = 0): array
{
    $course = Course::query()->where('code', 'tyt_tarih')->firstOrFail();

    $node = UnitNode::query()
        ->whereIn('unit_id', $course->units()->pluck('id'))
        ->orderBy('sort_order')
        ->skip($nodeIndex)
        ->first();

    app('auth')->forgetGuards();

    $sessionId = test()->withToken(test()->token)
        ->postJson('/api/v1/sessions', ['node_id' => $node->id])
        ->json('data.session_id');

    foreach (StudySession::query()->where('uuid', $sessionId)->firstOrFail()->items as $item) {
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

        test()->withToken(test()->token)->postJson("/api/v1/sessions/{$sessionId}/answers", [
            'exercise_id' => $item->exercise_id, 'answer' => $answer, 'elapsed_ms' => 5000,
        ]);
    }

    return test()->withToken(test()->token)
        ->postJson("/api/v1/sessions/{$sessionId}/complete")
        ->json('data');
}

it('yeni kullanıcının istatistikleri sıfırdan başlar', function (): void {
    $stats = $this->withToken($this->token)->getJson('/api/v1/me/stats')->assertOk()->json('data');

    expect($stats['level']['current'])->toBe(1)
        ->and($stats['level']['total_xp'])->toBe(0)
        ->and($stats['streak']['current'])->toBe(0)
        ->and($stats['activity']['total_sessions'])->toBe(0);
});

it('tur sonrası istatistikler güncellenir', function (): void {
    completeRound();

    app('auth')->forgetGuards();
    $stats = $this->withToken($this->token)->getJson('/api/v1/me/stats')->assertOk()->json('data');

    expect($stats['activity']['total_sessions'])->toBe(1)
        ->and($stats['activity']['perfect_sessions'])->toBe(1)
        ->and($stats['activity']['total_correct'])->toBeGreaterThan(0)
        ->and($stats['activity']['completed_nodes'])->toBe(1)
        ->and($stats['streak']['current'])->toBe(1)
        ->and($stats['level']['total_xp'])->toBeGreaterThan(0)
        // İlerleme çubuğunun alt ve üst sınırı.
        ->and($stats['level']['next_level_xp'])->toBeGreaterThan($stats['level']['level_start_xp']);
});

it('ilk tur "İlk Çalışma" rozetini kazandırır', function (): void {
    $summary = completeRound();

    $codes = collect($summary['badges_earned'])->pluck('code');

    expect($codes)->toContain('ilk_calisma')
        // Kutlama aynı yanıtta geliyor; rozet için ayrı istek yok.
        ->and($summary['badges_earned'][0])->toHaveKeys(['code', 'name', 'icon', 'tier']);
});

it('aynı rozet ikinci kez kazanılmaz', function (): void {
    completeRound(0);
    $second = completeRound(1);

    $codes = collect($second['badges_earned'])->pluck('code');

    expect($codes)->not->toContain('ilk_calisma')
        ->and(DB::table('user_badges')->where('badge_id', DB::table('badges')
            ->where('code', 'ilk_calisma')->value('id'))->count())->toBe(1);
});

it('rozet ızgarası kilitlilerde ilerleme gösterir', function (): void {
    completeRound();

    app('auth')->forgetGuards();
    $data = $this->withToken($this->token)->getJson('/api/v1/me/badges')->assertOk()->json('data');

    expect($data['total_count'])->toBe(8)
        ->and($data['earned_count'])->toBeGreaterThanOrEqual(1);

    $earned = collect($data['badges'])->firstWhere('code', 'ilk_calisma');
    $locked = collect($data['badges'])->firstWhere('code', 'dogru_100');

    expect($earned['earned'])->toBeTrue()
        ->and($earned)->not->toHaveKey('progress')
        // Kilitli rozette "73/100" göstermek gri ikondan çok daha motive edici.
        ->and($locked['earned'])->toBeFalse()
        ->and($locked['progress']['target'])->toBe(100)
        ->and($locked['progress']['current'])->toBeGreaterThan(0);
});

it('ünite bitince "İlk Ünite" rozeti gelir', function (): void {
    $course = Course::query()->where('code', 'tyt_tarih')->firstOrFail();
    $nodeCount = UnitNode::query()->whereIn('unit_id', $course->units()->pluck('id'))->count();

    $lastSummary = [];

    for ($i = 0; $i < $nodeCount; $i++) {
        $lastSummary = completeRound($i);
    }

    expect(collect($lastSummary['badges_earned'])->pluck('code'))->toContain('ilk_unite');
});

it('konu analizi ücretsiz planda özet döner', function (): void {
    completeRound();

    app('auth')->forgetGuards();
    $data = $this->withToken($this->token)->getJson('/api/v1/me/topics')->assertOk()->json('data');

    expect($data['detailed'])->toBeFalse()
        ->and($data['summary']['total'])->toBeGreaterThan(0)
        ->and($data)->not->toHaveKey('topics')
        // En zayıf konu ücretsizde de gösteriliyor: premium'un ne verdiğini
        // somut kılan örnek bu.
        ->and($data)->toHaveKey('weakest_topic');
});

it('konu analizi premium planda detay döner', function (): void {
    completeRound();

    config(['tekrarla.billing.revenuecat.webhook_secret' => 'test-secret']);

    $this->postJson('/api/v1/webhooks/revenuecat', ['event' => [
        'id' => 'evt-profil', 'type' => 'INITIAL_PURCHASE',
        'app_user_id' => DB::table('users')->value('uuid'),
        'product_id' => 'tekrarla_premium_yearly',
        'event_timestamp_ms' => now()->getTimestampMs(),
        'expiration_at_ms' => now()->addYear()->getTimestampMs(),
    ]], ['Authorization' => 'test-secret'])->assertOk();

    app('auth')->forgetGuards();
    $data = $this->withToken($this->token)->getJson('/api/v1/me/topics')->assertOk()->json('data');

    expect($data['detailed'])->toBeTrue()
        ->and($data['topics'])->not->toBeEmpty()
        ->and($data['topics'][0])->toHaveKeys(['topic', 'subject', 'accuracy', 'mastery']);
});

it('konu analizi derse göre filtrelenebilir', function (): void {
    completeRound();

    $tarih = Course::query()->where('code', 'tyt_tarih')->firstOrFail();
    $matematik = Course::query()->where('code', 'ayt_matematik')->firstOrFail();

    app('auth')->forgetGuards();

    $tarihData = $this->withToken($this->token)
        ->getJson("/api/v1/me/topics?course_id={$tarih->id}")->json('data');

    $matData = $this->withToken($this->token)
        ->getJson("/api/v1/me/topics?course_id={$matematik->id}")->json('data');

    expect($tarihData['summary']['total'])->toBeGreaterThan(0)
        ->and($matData['summary']['total'])->toBe(0);
});
