<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Modules\Learning\Infrastructure\Eloquent\Model\SessionItem;
use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/*
 | Oyun döngüsünün tamamı: tur başlat → cevapla → tamamla.
 | Tasarımdaki çalışma ve sonuç ekranlarının sözleşmesi burada kilitli.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->token = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'oyun-testi',
        'platform' => 'ios',
    ])->json('data.token');

    $this->withToken($this->token)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks',
        'field' => 'say',
        'grade' => '11',
    ]);

    $course = Course::query()->where('code', 'tyt_tarih')->firstOrFail();
    $this->node = UnitNode::query()
        ->whereIn('unit_id', $course->units()->pluck('id'))
        ->orderBy('sort_order')
        ->firstOrFail();
});

/** Oturumu başlatır ve session id'sini döner. */
function startSession(?int $nodeId = null, ?string $key = null): string
{
    $headers = $key !== null ? ['Idempotency-Key' => $key] : [];

    return test()->withToken(test()->token)
        ->withHeaders($headers)
        ->postJson('/api/v1/sessions', ['node_id' => $nodeId ?? test()->node->id])
        ->assertCreated()
        ->json('data.session_id');
}

/** Bir soruyu cevap anahtarından türeterek doğru cevaplar. */
function answerCorrectly(string $sessionId, SessionItem $item): array
{
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

    return test()->withToken(test()->token)
        ->postJson("/api/v1/sessions/{$sessionId}/answers", [
            'exercise_id' => $item->exercise_id,
            'answer' => $answer,
            'elapsed_ms' => 5000,
        ])
        ->assertOk()
        ->json('data');
}

function sessionItems(string $sessionId)
{
    return StudySession::query()->where('uuid', $sessionId)->firstOrFail()->items()->get();
}

it('oturum başlatır ve cevap anahtarını ASLA sızdırmaz', function (): void {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/sessions', ['node_id' => $this->node->id])
        ->assertCreated();

    expect($response->json('data.items'))->toHaveCount($this->node->exercise_count)
        ->and($response->getContent())->not->toContain('answer_key')
        ->and($response->getContent())->not->toContain('correct_option_id');
});

it('aynı idempotency anahtarıyla ikinci tur açmaz', function (): void {
    $first = startSession(key: 'ayni-anahtar');
    $second = startSession(key: 'ayni-anahtar');

    expect($second)->toBe($first)
        ->and(StudySession::query()->count())->toBe(1);
});

it('doğru cevapta can düşmez, yanlışta düşer', function (): void {
    $sessionId = startSession();
    $items = sessionItems($sessionId);

    $correct = answerCorrectly($sessionId, $items[0]);
    expect($correct['is_correct'])->toBeTrue()
        ->and($correct)->not->toHaveKey('hearts');   // can dokunulmadı

    $wrong = $this->withToken($this->token)
        ->postJson("/api/v1/sessions/{$sessionId}/answers", [
            'exercise_id' => $items[1]->exercise_id,
            'answer' => ['option_id' => 'gecersiz', 'value' => 'gecersiz'],
            'elapsed_ms' => 5000,
        ])->assertOk()->json('data');

    expect($wrong['is_correct'])->toBeFalse()
        ->and($wrong['hearts']['hearts'])->toBe(4)
        ->and($wrong['explanation'])->not->toBeNull();
});

it('aynı cevabı tekrar göndermek canı iki kez düşürmez', function (): void {
    // Offline'dan dönen istemcinin tetiklediği senaryo — hata değil, beklenen.
    $sessionId = startSession();
    $item = sessionItems($sessionId)[0];

    $payload = ['exercise_id' => $item->exercise_id, 'answer' => ['option_id' => 'yanlis', 'value' => 'yanlis'], 'elapsed_ms' => 5000];

    $first = $this->withToken($this->token)->postJson("/api/v1/sessions/{$sessionId}/answers", $payload)->json('data');
    $second = $this->withToken($this->token)->postJson("/api/v1/sessions/{$sessionId}/answers", $payload)->json('data');

    expect($first['hearts']['hearts'])->toBe(4)
        ->and($second['idempotent_replay'])->toBeTrue()
        ->and((int) DB::table('user_hearts')->value('hearts'))->toBe(4);
});

it('insan-dışı hızda cevabı şüpheli işaretler', function (): void {
    $sessionId = startSession();
    $item = sessionItems($sessionId)[0];

    $result = $this->withToken($this->token)
        ->postJson("/api/v1/sessions/{$sessionId}/answers", [
            'exercise_id' => $item->exercise_id,
            'answer' => ['option_id' => 'x', 'value' => false],
            'elapsed_ms' => 50,
        ])->json('data');

    expect($result['suspicious'])->toBeTrue();
});

it('turu tamamlar ve sonuç ekranının tamamını tek yanıtta döner', function (): void {
    $sessionId = startSession();

    foreach (sessionItems($sessionId) as $item) {
        answerCorrectly($sessionId, $item);
    }

    $summary = $this->withToken($this->token)
        ->postJson("/api/v1/sessions/{$sessionId}/complete")
        ->assertOk()
        ->json('data');

    expect($summary['score']['accuracy'])->toBe(100)
        ->and($summary['score']['is_perfect'])->toBeTrue()
        ->and($summary['xp']['breakdown'])->toHaveKeys(['base', 'perfect', 'first_completion'])
        ->and($summary['level']['after'])->toBeGreaterThanOrEqual(1)
        ->and($summary['streak']['days'])->toBe(1)
        ->and($summary['streak']['extended_today'])->toBeTrue()
        ->and($summary['unlocked_nodes'])->not->toBeEmpty()
        ->and($summary['unit']['completion_percent'])->toBeGreaterThan(0);
});

it('tamamlanan tur ikinci kez tamamlanamaz', function (): void {
    $sessionId = startSession();
    foreach (sessionItems($sessionId) as $item) {
        answerCorrectly($sessionId, $item);
    }

    $this->withToken($this->token)->postJson("/api/v1/sessions/{$sessionId}/complete")->assertOk();

    $this->withToken($this->token)
        ->postJson("/api/v1/sessions/{$sessionId}/complete")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'SESSION_ALREADY_COMPLETED');
});

it('aynı oturum iki kez XP üretemez', function (): void {
    $sessionId = startSession();
    foreach (sessionItems($sessionId) as $item) {
        answerCorrectly($sessionId, $item);
    }
    $this->withToken($this->token)->postJson("/api/v1/sessions/{$sessionId}/complete");

    // Defterde tek kayıt: unique(source_type, source_id) bunu garanti eder.
    expect(DB::table('xp_ledger')->count())->toBe(1);
});

it('tamamlanan tur yolu açar ve ilerlemeyi yazar', function (): void {
    $sessionId = startSession();
    foreach (sessionItems($sessionId) as $item) {
        answerCorrectly($sessionId, $item);
    }
    $this->withToken($this->token)->postJson("/api/v1/sessions/{$sessionId}/complete");

    $course = Course::query()->where('code', 'tyt_tarih')->firstOrFail();
    $path = $this->withToken($this->token)->getJson("/api/v1/courses/{$course->id}/path")->json('data');

    $nodes = $path['units'][0]['nodes'];

    expect($nodes[0]['state'])->toBe('completed')
        ->and($nodes[1]['state'])->toBe('available')   // artık kilitli değil
        ->and($path['units'][0]['completed_nodes'])->toBe(1);
});

it('yanlışlar tekrar kuyruğuna düşer', function (): void {
    $sessionId = startSession();
    $items = sessionItems($sessionId);

    $this->withToken($this->token)->postJson("/api/v1/sessions/{$sessionId}/answers", [
        'exercise_id' => $items[0]->exercise_id,
        'answer' => ['option_id' => 'yanlis', 'value' => 'yanlis'],
        'elapsed_ms' => 5000,
    ]);

    foreach ($items->skip(1) as $item) {
        answerCorrectly($sessionId, $item);
    }

    $this->withToken($this->token)->postJson("/api/v1/sessions/{$sessionId}/complete");

    expect(DB::table('review_queue')->where('exercise_id', $items[0]->exercise_id)->exists())->toBeTrue()
        ->and(DB::table('review_queue')->where('exercise_id', $items[1]->exercise_id)->exists())->toBeFalse();
});

it('konu ustalığını günceller', function (): void {
    $sessionId = startSession();
    foreach (sessionItems($sessionId) as $item) {
        answerCorrectly($sessionId, $item);
    }
    $this->withToken($this->token)->postJson("/api/v1/sessions/{$sessionId}/complete");

    $stats = DB::table('user_topic_stats')->get();

    expect($stats)->not->toBeEmpty()
        ->and($stats->every(fn ($s): bool => $s->mastery === 'strong'))->toBeTrue();
});

it('başka kullanıcının oturumuna erişilemez', function (): void {
    $sessionId = startSession();

    $otherToken = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'baska-cihaz',
        'platform' => 'android',
    ])->json('data.token');

    actingWithToken($otherToken)
        ->postJson("/api/v1/sessions/{$sessionId}/complete")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'SESSION_NOT_FOUND');
});

it('turu yarıda bırakabilir', function (): void {
    $sessionId = startSession();

    $this->withToken($this->token)
        ->postJson("/api/v1/sessions/{$sessionId}/abandon")
        ->assertOk()
        ->assertJsonPath('data.status', 'abandoned');
});

it('can bakiyesini raporlar', function (): void {
    $this->withToken($this->token)
        ->getJson('/api/v1/hearts')
        ->assertOk()
        ->assertJsonPath('data.hearts', 5)
        ->assertJsonPath('data.max', 5);
});
