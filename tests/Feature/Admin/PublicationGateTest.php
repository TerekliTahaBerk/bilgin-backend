<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Contract\ExercisePool;
use App\Modules\Catalog\Domain\Enum\SelectionMode;
use App\Modules\Catalog\Domain\Selection\PoolCriteria;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Topic;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Unit;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Shared\Domain\Enum\PublishStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
 | YAYIN KAPISI — aday havuzu anlamı.
 |
 | Buradaki testlerin tamamı tek bir gerilimi koruyor: yayın doğrulaması
 | ünitenin KENDİ taslak sorularını görmeli, öğrenci ÇALIŞMA ANI ise asla
 | görmemeli. İkisinden biri kayarsa ya ilk yayın imkânsızlaşır ya da
 | öğrenciye yayınlanmamış soru gider; ikisi de sessiz hatalardır.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->tokens = collect([
        'admin' => 'admin@tekrarla.test',
        'editor' => 'editor@tekrarla.test',
        'denetci' => 'denetci@tekrarla.test',
    ])->map(fn (string $email): string => $this->postJson('/api/admin/v1/auth/login', [
        'email' => $email,
        'password' => 'tekrarla-local',
    ])->assertOk()->json('data.token'));
});

function gatePanel(string $role): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(test()->tokens[$role]);
}

/** Kendi konusu olan, hiç sorusu olmayan taze bir taslak ünite. */
function gateUnit(string $topicCode = 'iklim'): Unit
{
    $course = Course::query()->where('code', 'tyt_cografya')->firstOrFail();

    $topic = Topic::query()->create([
        'subject_id' => $course->subject_id,
        'code' => $topicCode,
        'name' => 'İklim Bilgisi',
        'sort_order' => 1,
    ]);

    $unitId = gatePanel('editor')
        ->postJson('/api/admin/v1/units', [
            'course_code' => 'tyt_cografya',
            'template_code' => 'hafif_unite',
            'title' => 'Aday Havuzu Ünitesi',
            'topic_ids' => [$topic->id],
        ])->assertCreated()->json('data.id');

    $unit = Unit::query()->findOrFail($unitId);
    $unit->setRelation('seedTopic', $topic);

    return $unit;
}

/** Üniteye ait soru üretir; varsayılan durum TASLAK. */
function gateExercise(Unit $unit, Topic $topic, PublishStatus $status = PublishStatus::Draft, int $difficulty = 2): Exercise
{
    return Exercise::query()->create([
        'uuid' => (string) Str::uuid(),
        'topic_id' => $topic->id,
        'owner_course_id' => $unit->course_id,
        'owner_unit_id' => $unit->id,
        'type' => 'multiple_choice',
        'content' => [
            'stem' => 'Kıta ikliminin belirgin özelliği nedir?',
            'options' => [
                ['id' => 'a', 'text' => 'Yazlar sıcak, kışlar soğuk'],
                ['id' => 'b', 'text' => 'Her mevsim yağışlı'],
                ['id' => 'c', 'text' => 'Sıcaklık farkı yok'],
                ['id' => 'd', 'text' => 'Kışlar ılık'],
            ],
        ],
        'answer_key' => ['correct_option_id' => 'a'],
        'difficulty' => $difficulty,
        'applicable_scopes' => ['tyt'],
        'status' => $status,
        'version' => 1,
    ]);
}

/** @return array<int, string> node id => status */
function gateNodeStatuses(Unit $unit): array
{
    return $unit->nodes()->get()
        ->mapWithKeys(fn (UnitNode $n): array => [$n->id => $n->status->value])
        ->all();
}

/** @return array<int, string> exercise id => status */
function gateExerciseStatuses(Unit $unit): array
{
    return Exercise::query()
        ->where('owner_unit_id', $unit->id)
        ->orderBy('id')
        ->get()
        ->mapWithKeys(fn (Exercise $e): array => [$e->id => $e->status->value])
        ->all();
}

it('önizleme, ünitenin kendi TASLAK sorularını aday sayar', function (): void {
    // Bu olmadan ilk yayın imkânsızdı: yeni yazılan her soru taslak doğar,
    // yayın kapısı ise yayından önce çalışır.
    $unit = gateUnit();
    $topic = $unit->getRelation('seedTopic');

    for ($i = 0; $i < 12; $i++) {
        gateExercise($unit, $topic);
    }

    $node = $unit->nodes()->orderBy('sort_order')->firstOrFail();

    $response = gatePanel('editor')
        ->getJson("/api/admin/v1/nodes/{$node->id}/preview-selection")
        ->assertOk();

    expect($response->json('data.available'))->toBeGreaterThanOrEqual($node->exercise_count)
        ->and($response->json('data.passes'))->toBeTrue();
});

it('çalışma anı havuzu taslak soruyu GÖRMEZ', function (): void {
    // Aday gevşemesi yalnızca yayın doğrulamasına aittir. publicationUnitId
    // boş bırakıldığında havuz yayındakilerle sınırlı kalmalı.
    $unit = gateUnit();
    $topic = $unit->getRelation('seedTopic');

    for ($i = 0; $i < 12; $i++) {
        gateExercise($unit, $topic);
    }

    $pool = app(ExercisePool::class);
    $runtime = new PoolCriteria(topicIds: [$topic->id], scope: 'tyt');
    $publication = new PoolCriteria(topicIds: [$topic->id], scope: 'tyt', publicationUnitId: $unit->id);

    expect($pool->count($runtime))->toBe(0)
        ->and($pool->count($publication))->toBe(12);
});

it('arşivlenmiş soru yayın adayı DEĞİLDİR', function (): void {
    $unit = gateUnit();
    $topic = $unit->getRelation('seedTopic');

    gateExercise($unit, $topic, PublishStatus::Draft);
    gateExercise($unit, $topic, PublishStatus::Review);
    gateExercise($unit, $topic, PublishStatus::Archived);

    $pool = app(ExercisePool::class);

    expect($pool->count(new PoolCriteria(
        topicIds: [$topic->id],
        scope: 'tyt',
        publicationUnitId: $unit->id,
    )))->toBe(2);
});

it('sabit liste kuralı yayın doğrulamasında taslak kimliği görür', function (): void {
    $unit = gateUnit();
    $topic = $unit->getRelation('seedTopic');

    $draft = gateExercise($unit, $topic);
    $archived = gateExercise($unit, $topic, PublishStatus::Archived);

    $node = $unit->nodes()->orderBy('sort_order')->firstOrFail();
    $node->update([
        'exercise_count' => 1,
        'selection_rule' => [
            'mode' => SelectionMode::Fixed->value,
            'count' => 1,
            'exercise_ids' => [$draft->id, $archived->id],
        ],
    ]);

    $response = gatePanel('editor')
        ->getJson("/api/admin/v1/nodes/{$node->id}/preview-selection")
        ->assertOk();

    // Taslak sayılır, arşiv sayılmaz: tam olarak bir aday.
    expect($response->json('data.available'))->toBe(1)
        ->and($response->json('data.passes'))->toBeTrue();

    $pool = app(ExercisePool::class);

    expect($pool->findSelectableByIds([$draft->id, $archived->id]))->toBe([])
        ->and($pool->findSelectableByIds([$draft->id, $archived->id], $unit->id))->toHaveCount(1);
});

it('önizleme veritabanında hiçbir durumu değiştirmez', function (): void {
    $unit = gateUnit();
    $topic = $unit->getRelation('seedTopic');

    for ($i = 0; $i < 12; $i++) {
        gateExercise($unit, $topic);
    }

    $before = [
        'unit' => $unit->status->value,
        'nodes' => gateNodeStatuses($unit),
        'exercises' => gateExerciseStatuses($unit),
    ];

    foreach ($unit->nodes()->get() as $node) {
        gatePanel('editor')
            ->getJson("/api/admin/v1/nodes/{$node->id}/preview-selection")
            ->assertOk();
    }

    expect($unit->fresh()->status->value)->toBe($before['unit'])
        ->and(gateNodeStatuses($unit))->toBe($before['nodes'])
        ->and(gateExerciseStatuses($unit))->toBe($before['exercises']);
});

it('yetersiz havuzda yayın 422 döner ve HİÇBİR durum değişmez', function (): void {
    $unit = gateUnit();
    $topic = $unit->getRelation('seedTopic');

    // Dört node en az 5 soru istiyor; üç tane bilerek yetersiz.
    gateExercise($unit, $topic);
    gateExercise($unit, $topic);
    gateExercise($unit, $topic);

    $before = [
        'unit' => $unit->status->value,
        'nodes' => gateNodeStatuses($unit),
        'exercises' => gateExerciseStatuses($unit),
    ];

    $response = gatePanel('denetci')
        ->postJson("/api/admin/v1/units/{$unit->id}/publish")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CONTENT_NOT_PUBLISHABLE');

    expect($response->json('error.details.blocking'))->not->toBeEmpty()
        ->and($unit->fresh()->status->value)->toBe($before['unit'])
        ->and(gateNodeStatuses($unit))->toBe($before['nodes'])
        ->and(gateExerciseStatuses($unit))->toBe($before['exercises']);
});

it('başarılı yayın üniteyi, adımları ve uygun soruları yayınlar', function (): void {
    $unit = gateUnit();
    $topic = $unit->getRelation('seedTopic');

    for ($i = 0; $i < 12; $i++) {
        gateExercise($unit, $topic);
    }

    $review = gateExercise($unit, $topic, PublishStatus::Review);
    $archived = gateExercise($unit, $topic, PublishStatus::Archived);

    gatePanel('denetci')
        ->postJson("/api/admin/v1/units/{$unit->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.published_nodes', $unit->nodes()->count());

    expect($unit->fresh()->status)->toBe(PublishStatus::Published)
        ->and(collect(gateNodeStatuses($unit))->unique()->values()->all())->toBe(['published'])
        ->and($review->fresh()->status)->toBe(PublishStatus::Published);

    // ARŞİV YAYINA DÖNMEZ: editörün bilinçli kararı bir sonraki yayında
    // sessizce geri alınamaz.
    expect($archived->fresh()->status)->toBe(PublishStatus::Archived);
});

it('arşivlenen soru yayından sonra da arşivde kalır', function (): void {
    $unit = gateUnit();
    $topic = $unit->getRelation('seedTopic');

    for ($i = 0; $i < 12; $i++) {
        gateExercise($unit, $topic);
    }

    $victim = gateExercise($unit, $topic, PublishStatus::Published);

    gatePanel('editor')
        ->deleteJson("/api/admin/v1/exercises/{$victim->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'archived');

    gatePanel('denetci')
        ->postJson("/api/admin/v1/units/{$unit->id}/publish")
        ->assertOk();

    expect($victim->fresh()->status)->toBe(PublishStatus::Archived);
});

it('yayındaki genel havuz soruları kuralın izin verdiği yerde sayılır', function (): void {
    // Havuz modelinin asıl faydası: ünitenin kendi sorusu olmasa bile konuya
    // ait YAYINDAKİ sorular kurala yeter.
    $unit = gateUnit();
    $topic = $unit->getRelation('seedTopic');

    $foreignUnitId = Unit::query()
        ->where('course_id', '!=', $unit->course_id)
        ->value('id');

    for ($i = 0; $i < 12; $i++) {
        $exercise = gateExercise($unit, $topic, PublishStatus::Published);
        // Sahipliği başka üniteye taşı: aday gevşemesi devre dışı kalsın,
        // yalnızca "yayında" olması sayılsın.
        $exercise->update(['owner_unit_id' => $foreignUnitId]);
    }

    $node = $unit->nodes()->orderBy('sort_order')->firstOrFail();

    $response = gatePanel('editor')
        ->getJson("/api/admin/v1/nodes/{$node->id}/preview-selection")
        ->assertOk();

    expect($response->json('data.passes'))->toBeTrue();
});

it('başka ünitenin taslak soruları aday sayılmaz', function (): void {
    // Aday gevşemesi YAYINLANAN üniteye özeldir. Aynı konuya ait başka bir
    // ünitenin taslakları sayılsaydı, kapı hiç yazılmamış içerikle geçerdi.
    $unit = gateUnit();
    $topic = $unit->getRelation('seedTopic');

    $other = gateUnit('kesif_baska_konu');

    for ($i = 0; $i < 12; $i++) {
        // Sahibi DİĞER ünite, konusu bizimki: konu filtresine uyuyor ama
        // aday olmamalı.
        gateExercise($other, $topic);
    }

    $pool = app(ExercisePool::class);

    expect($pool->count(new PoolCriteria(
        topicIds: [$topic->id],
        scope: 'tyt',
        publicationUnitId: $unit->id,
    )))->toBe(0);

    $node = $unit->nodes()->orderBy('sort_order')->firstOrFail();

    gatePanel('editor')
        ->getJson("/api/admin/v1/nodes/{$node->id}/preview-selection")
        ->assertOk()
        ->assertJsonPath('data.available', 0)
        ->assertJsonPath('data.passes', false);
});

it('ünitenin kendi İNCELEMEDEKİ soruları aday sayılır', function (): void {
    $unit = gateUnit();
    $topic = $unit->getRelation('seedTopic');

    for ($i = 0; $i < 12; $i++) {
        gateExercise($unit, $topic, PublishStatus::Review);
    }

    $node = $unit->nodes()->orderBy('sort_order')->firstOrFail();

    $response = gatePanel('editor')
        ->getJson("/api/admin/v1/nodes/{$node->id}/preview-selection")
        ->assertOk();

    expect($response->json('data.available'))->toBeGreaterThanOrEqual($node->exercise_count)
        ->and($response->json('data.passes'))->toBeTrue();

    // Çalışma anı yine görmemeli.
    expect(app(ExercisePool::class)->count(
        new PoolCriteria(topicIds: [$topic->id], scope: 'tyt')
    ))->toBe(0);
});

it('aday olmak diğer seçim ölçütlerini atlatmaz', function (): void {
    // "Aday" yalnızca DURUM koşulunu gevşetir; konu, kapsam ve zorluk
    // filtreleri aynen uygulanmalı.
    $unit = gateUnit();
    $topic = $unit->getRelation('seedTopic');

    for ($i = 0; $i < 6; $i++) {
        gateExercise($unit, $topic, PublishStatus::Draft, difficulty: 5);
    }

    $pool = app(ExercisePool::class);

    expect($pool->count(new PoolCriteria(
        topicIds: [$topic->id],
        scope: 'tyt',
        difficultyMin: 1,
        difficultyMax: 2,
        publicationUnitId: $unit->id,
    )))->toBe(0);

    expect($pool->count(new PoolCriteria(
        topicIds: [$topic->id],
        scope: 'ayt',
        publicationUnitId: $unit->id,
    )))->toBe(0);

    expect($pool->count(new PoolCriteria(
        topicIds: [$topic->id],
        scope: 'tyt',
        publicationUnitId: $unit->id,
    )))->toBe(6);
});
