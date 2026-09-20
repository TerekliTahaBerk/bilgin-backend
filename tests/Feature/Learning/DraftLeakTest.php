<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use App\Shared\Domain\Enum\PublishStatus;
use Database\Seeders\DatabaseSeeder;

/*
 | ÇALIŞMA ANINDA TASLAK SIZINTISI.
 |
 | Yayın kapısı artık ünitenin kendi taslak sorularını aday sayıyor. Bu
 | gevşemenin öğrenciye ulaşmaması, çalışma anı kurulumlarının
 | publicationUnitId'yi BOŞ bırakmasına bağlı — yani bir gelenek.
 |
 | Havuz seviyesindeki testler bu geleneği koruyamaz: biri StartStudySession
 | içine publicationUnitId eklerse orada hiçbir şey kırılmaz. Buradaki
 | testler gerçek HTTP yolundan geçiyor, tam da o yüzden.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->token = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'taslak-sizinti', 'platform' => 'ios',
    ])->json('data.token');

    $this->withToken($this->token)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks', 'field' => 'soz', 'grade' => '11',
    ]);
});

/** Yayındaki bir sorunun taslak kopyası — aynı konu, aynı tip, aynı zorluk. */
function draftCloneOf(Exercise $source): Exercise
{
    return Exercise::query()->create([
        'uuid' => (string) Str::uuid(),
        'topic_id' => $source->topic_id,
        'owner_course_id' => $source->owner_course_id,
        'owner_unit_id' => $source->owner_unit_id,
        'type' => $source->type,
        'content' => $source->content,
        'answer_key' => $source->answer_key,
        'explanation' => 'taslak kopya',
        'difficulty' => $source->difficulty,
        'applicable_scopes' => $source->applicable_scopes,
        'status' => PublishStatus::Draft,
    ]);
}

it('öğrenci turu YAYINLANMIŞ ünitenin taslak sorusunu almaz', function (): void {
    $course = Course::query()->where('code', 'tyt_tarih')->firstOrFail();
    $unitIds = $course->units()->pluck('id');

    $published = Exercise::query()
        ->whereIn('owner_unit_id', $unitIds)
        ->where('status', PublishStatus::Published)
        ->get();

    expect($published)->not->toBeEmpty();

    // Havuzu taslak kopyalarla dolduruyoruz: sızıntı varsa seçilme
    // olasılığı yüksek olsun.
    $draftIds = $published->take(20)->map(fn (Exercise $e): int => draftCloneOf($e)->id)->all();

    expect($draftIds)->not->toBeEmpty();

    $node = UnitNode::query()->whereIn('unit_id', $unitIds)->orderBy('sort_order')->firstOrFail();

    app('auth')->forgetGuards();

    $sessionId = $this->withToken($this->token)
        ->postJson('/api/v1/sessions', ['node_id' => $node->id])
        ->assertCreated()
        ->json('data.session_id');

    $served = StudySession::query()->where('uuid', $sessionId)->firstOrFail()
        ->items()->pluck('exercise_id')->all();

    expect($served)->not->toBeEmpty()
        ->and(array_intersect($served, $draftIds))->toBeEmpty();
});

it('öğrenci turu BAŞKA ünitenin taslak sorusunu da almaz', function (): void {
    $course = Course::query()->where('code', 'tyt_tarih')->firstOrFail();
    $unitIds = $course->units()->pluck('id');

    $source = Exercise::query()
        ->whereIn('owner_unit_id', $unitIds)
        ->where('status', PublishStatus::Published)
        ->firstOrFail();

    // Başka bir üniteye ait ama AYNI konudan taslaklar.
    $otherUnitId = $course->units()->where('id', '!=', $source->owner_unit_id)->value('id')
        ?? $source->owner_unit_id;

    $draftIds = [];
    for ($i = 0; $i < 10; $i++) {
        $clone = draftCloneOf($source);
        $clone->update(['owner_unit_id' => $otherUnitId]);
        $draftIds[] = $clone->id;
    }

    $node = UnitNode::query()->where('unit_id', $source->owner_unit_id)
        ->orderBy('sort_order')->firstOrFail();

    app('auth')->forgetGuards();

    $sessionId = $this->withToken($this->token)
        ->postJson('/api/v1/sessions', ['node_id' => $node->id])
        ->assertCreated()
        ->json('data.session_id');

    $served = StudySession::query()->where('uuid', $sessionId)->firstOrFail()
        ->items()->pluck('exercise_id')->all();

    expect(array_intersect($served, $draftIds))->toBeEmpty();
});

it('deneme sınavı da taslak soru almaz', function (): void {
    // Deneme, blueprint seçicisinden geçiyor; o da aynı havuzu kullanıyor.
    $course = Course::query()->where('code', 'tyt_tarih')->firstOrFail();
    $unitIds = $course->units()->pluck('id');

    $published = Exercise::query()
        ->whereIn('owner_unit_id', $unitIds)
        ->where('status', PublishStatus::Published)
        ->get();

    $draftIds = $published->take(20)->map(fn (Exercise $e): int => draftCloneOf($e)->id)->all();

    app('auth')->forgetGuards();

    $blueprintId = $this->withToken($this->token)
        ->getJson('/api/v1/exam-simulations')->assertOk()->json('data.0.id');

    app('auth')->forgetGuards();

    $sessionId = $this->withToken($this->token)
        ->postJson('/api/v1/exam-simulations', ['blueprint_id' => $blueprintId])
        ->assertCreated()
        ->json('data.session_id');

    $served = StudySession::query()->where('uuid', $sessionId)->firstOrFail()
        ->items()->pluck('exercise_id')->all();

    expect($served)->not->toBeEmpty()
        ->and(array_intersect($served, $draftIds))->toBeEmpty();
});

it('arşivlenen soru yayındaki turdan düşer', function (): void {
    // Arşiv, çalışma anında da aday değildir.
    $course = Course::query()->where('code', 'tyt_tarih')->firstOrFail();
    $unitIds = $course->units()->pluck('id');

    $archived = Exercise::query()
        ->whereIn('owner_unit_id', $unitIds)
        ->where('status', PublishStatus::Published)
        ->limit(5)
        ->get();

    $archivedIds = $archived->pluck('id')->all();
    Exercise::query()->whereIn('id', $archivedIds)->update(['status' => PublishStatus::Archived]);

    $node = UnitNode::query()->whereIn('unit_id', $unitIds)->orderBy('sort_order')->firstOrFail();

    app('auth')->forgetGuards();

    $sessionId = $this->withToken($this->token)
        ->postJson('/api/v1/sessions', ['node_id' => $node->id])
        ->assertCreated()
        ->json('data.session_id');

    $served = StudySession::query()->where('uuid', $sessionId)->firstOrFail()
        ->items()->pluck('exercise_id')->all();

    expect(array_intersect($served, $archivedIds))->toBeEmpty();
});
