<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Topic;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Unit;
use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use App\Shared\Domain\Enum\PublishStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->adminToken = $this->postJson('/api/admin/v1/auth/login', [
        'email' => 'admin@tekrarla.test', 'password' => 'tekrarla-local',
    ])->assertOk()->json('data.token');

    $this->studentToken = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'sapma-sonda', 'platform' => 'ios',
    ])->json('data.token');

    $this->withToken($this->studentToken)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks', 'field' => 'soz', 'grade' => '11',
    ]);
});

function probeExercise(Unit $unit, Topic $topic, PublishStatus $status): Exercise
{
    return Exercise::query()->create([
        'uuid' => (string) Str::uuid(),
        'topic_id' => $topic->id,
        'owner_course_id' => $unit->course_id,
        'owner_unit_id' => $unit->id,
        'type' => 'multiple_choice',
        'content' => ['stem' => 'Soru '.Str::random(6), 'options' => [
            ['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => 'B'],
        ]],
        'answer_key' => ['correct_option_id' => 'a'],
        'difficulty' => 2,
        'applicable_scopes' => ['tyt'],
        'status' => $status,
        'version' => 1,
    ]);
}

/*
 | ÖNİZLEME İKİ SAYI VERİR.
 |
 | Yayın kapısı ünitenin kendi taslaklarını aday sayıyor — ilk yayın ancak
 | böyle mümkün. Ama aynı sayıyı "öğrenci şu an ne alıyor" diye okumak
 | yanıltıcı: yayınlanmış bir ünitede soru arşivlenip yerine taslak
 | yazıldığında aday yeterli görünürken öğrenciye giden azalır.
 |
 | Bu, içerik ekibinin olağan davranışıyla tetiklenen sessiz bir sapmaydı.
 */

it('yayından sonra arşiv+taslak, önizlemenin canlı sayısına yansır', function (): void {
    // Kendi konusu olan taze bir ünite: havuz başka ünitelerden beslenmesin.
    $course = Course::query()->where('code', 'tyt_cografya')->firstOrFail();
    $topic = Topic::query()->create([
        'subject_id' => $course->subject_id,
        'code' => 'sonda_iklim', 'name' => 'Sonda İklim', 'sort_order' => 99,
    ]);

    app('auth')->forgetGuards();

    $unitId = $this->withToken($this->adminToken)->postJson('/api/admin/v1/units', [
        'course_code' => 'tyt_cografya',
        'template_code' => 'hafif_unite',
        'title' => 'Sapma Sondası Ünitesi',
        'topic_ids' => [$topic->id],
    ])->assertCreated()->json('data.id');

    $unit = Unit::query()->findOrFail($unitId);
    // En çok soru isteyen adımı hedefliyoruz.
    $node = $unit->nodes()->orderByDesc('exercise_count')->firstOrFail();
    $required = $node->exercise_count;

    // Bütün adımlara yetecek kadar YAYINDA soru → kapı geçsin.
    $live = [];
    for ($i = 0; $i < 12; $i++) {
        $live[] = probeExercise($unit, $topic, PublishStatus::Published)->id;
    }

    app('auth')->forgetGuards();
    $this->withToken($this->adminToken)
        ->postJson("/api/admin/v1/units/{$unitId}/publish")->assertOk();

    // Yayından SONRA: yarısı arşivleniyor, yerine taslak yazılıyor.
    // İçerik ekibinin olağan davranışı.
    // Yayındaki havuzu gerekenin ALTINA indir, açığı taslakla kapat.
    $keepLive = 4;
    Exercise::query()->whereIn('id', array_slice($live, $keepLive))
        ->update(['status' => PublishStatus::Archived]);

    for ($i = 0; $i < 8; $i++) {
        probeExercise($unit, $topic, PublishStatus::Draft);
    }

    app('auth')->forgetGuards();
    $preview = $this->withToken($this->adminToken)
        ->getJson("/api/admin/v1/nodes/{$node->id}/preview-selection")
        ->assertOk()->json('data');

    app('auth')->forgetGuards();
    $sessionId = $this->withToken($this->studentToken)
        ->postJson('/api/v1/sessions', ['node_id' => $node->id])
        ->assertCreated()->json('data.session_id');

    $served = StudySession::query()->where('uuid', $sessionId)
        ->firstOrFail()->items()->count();

    // Önizleme gerçeği söylüyor: canlı sayı öğrenciye gidenle aynı.
    expect($preview['live_available'])->toBe($served)
        ->and($preview['live_passes'])->toBeFalse()
        ->and($preview['live_warning'])->not->toBeNull()
        // Yayın kararı aday kipinde kalmalı: yayınlanınca kural doyuyor.
        ->and($preview['passes'])->toBeTrue();
});

it('sapma yokken uyarı çıkmaz', function (): void {
    // Sağlıklı ünitede iki sayı eşit; gereksiz uyarı editörü köreltir.
    $course = Course::query()->where('code', 'tyt_cografya')->firstOrFail();
    $topic = Topic::query()->create([
        'subject_id' => $course->subject_id,
        'code' => 'sonda_saglikli', 'name' => 'Sağlıklı Konu', 'sort_order' => 98,
    ]);

    app('auth')->forgetGuards();

    $unitId = $this->withToken($this->adminToken)->postJson('/api/admin/v1/units', [
        'course_code' => 'tyt_cografya',
        'template_code' => 'hafif_unite',
        'title' => 'Sağlıklı Ünite',
        'topic_ids' => [$topic->id],
    ])->assertCreated()->json('data.id');

    $unit = Unit::query()->findOrFail($unitId);

    for ($i = 0; $i < 12; $i++) {
        probeExercise($unit, $topic, PublishStatus::Published);
    }

    app('auth')->forgetGuards();
    $this->withToken($this->adminToken)
        ->postJson("/api/admin/v1/units/{$unitId}/publish")->assertOk();

    $node = $unit->nodes()->orderBy('sort_order')->firstOrFail();

    app('auth')->forgetGuards();
    $preview = $this->withToken($this->adminToken)
        ->getJson("/api/admin/v1/nodes/{$node->id}/preview-selection")
        ->assertOk()->json('data');

    expect($preview['live_available'])->toBe($preview['available'])
        ->and($preview['live_passes'])->toBeTrue()
        ->and($preview['live_warning'])->toBeNull();
});

it('yayınlanmamış ünitede canlı sayı sıfırdır, karar aday kipinde verilir', function (): void {
    // İlk yayın öncesi: hiçbir soru yayında değil. Canlı sayı bunu dürüstçe
    // söylemeli ama yayın kararını engellememeli.
    $course = Course::query()->where('code', 'tyt_cografya')->firstOrFail();
    $topic = Topic::query()->create([
        'subject_id' => $course->subject_id,
        'code' => 'sonda_ilk_yayin', 'name' => 'İlk Yayın Konusu', 'sort_order' => 97,
    ]);

    app('auth')->forgetGuards();

    $unitId = $this->withToken($this->adminToken)->postJson('/api/admin/v1/units', [
        'course_code' => 'tyt_cografya',
        'template_code' => 'hafif_unite',
        'title' => 'İlk Yayın Ünitesi',
        'topic_ids' => [$topic->id],
    ])->assertCreated()->json('data.id');

    $unit = Unit::query()->findOrFail($unitId);

    for ($i = 0; $i < 12; $i++) {
        probeExercise($unit, $topic, PublishStatus::Draft);
    }

    $node = $unit->nodes()->orderBy('sort_order')->firstOrFail();

    app('auth')->forgetGuards();
    $preview = $this->withToken($this->adminToken)
        ->getJson("/api/admin/v1/nodes/{$node->id}/preview-selection")
        ->assertOk()->json('data');

    expect($preview['passes'])->toBeTrue()
        ->and($preview['live_available'])->toBe(0);
});
