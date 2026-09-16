<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Topic;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Unit;
use App\Shared\Domain\Enum\PublishStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 | Panelde soru düzenleme.
 |
 | Düzenleme yayındaki içeriğe dokunuyor: hatalı bir kayıt öğrenciye
 | çözülemez soru olarak gider, yanlış bir silme geçmiş istatistiği bozar.
 | Testlerin çoğu bu iki riski karşılıyor.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->tokens = collect([
        'admin' => 'admin@tekrarla.test',
        'editor' => 'editor@tekrarla.test',
        'denetci' => 'denetci@tekrarla.test',
    ])->map(fn (string $email): string => $this->postJson('/api/admin/v1/auth/login', [
        'email' => $email, 'password' => 'tekrarla-local',
    ])->assertOk()->json('data.token'));

    $this->unit = Unit::query()->where('title', 'İlk ve Orta Çağlarda Türk Dünyası')->firstOrFail();
    $this->topic = Topic::query()->where('code', 'ilk_turk_devletleri')->firstOrFail();
});

function panel(string $role): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(test()->tokens[$role]);
}

function validExercise(array $overrides = []): array
{
    return array_merge([
        'type' => 'multiple_choice',
        'topic_id' => test()->topic->id,
        'owner_unit_id' => test()->unit->id,
        'content' => [
            'stem' => 'Kavimler Göçü hangi yılda gerçekleşti?',
            'options' => [
                ['id' => 'a', 'text' => '375'],
                ['id' => 'b', 'text' => '395'],
                ['id' => 'c', 'text' => '451'],
            ],
        ],
        'answer_key' => ['correct_option_id' => 'a'],
        'explanation' => '375 Kavimler Göçü İlk Çağ\'ın sonu kabul edilir.',
        'difficulty' => 2,
        'applicable_scopes' => ['tyt'],
    ], $overrides);
}

it('ünitenin sorularını istatistikleriyle listeler', function (): void {
    $response = panel('editor')
        ->getJson("/api/admin/v1/units/{$this->unit->id}/exercises")
        ->assertOk();

    $exercises = $response->json('data.exercises');

    expect($exercises)->toHaveCount(44)
        ->and($exercises[0])->toHaveKeys(['id', 'type', 'topic', 'difficulty', 'preview', 'stats']);
});

it('tek soruyu cevap anahtarıyla döner', function (): void {
    // Panelde anahtar GÖRÜNÜR — editör onu düzenliyor. Öğrenci API'sinde asla.
    $exercise = Exercise::query()->where('type', 'multiple_choice')->firstOrFail();

    $response = panel('editor')->getJson("/api/admin/v1/exercises/{$exercise->id}")->assertOk();

    expect($response->json('data.answer_key'))->toHaveKey('correct_option_id');
});

it('editör soru ekleyebilir ve taslak olarak açılır', function (): void {
    // Yayın kararı denetçinin; yeni soru doğrudan öğrenciye gitmez.
    $response = panel('editor')
        ->postJson('/api/admin/v1/exercises', validExercise())
        ->assertCreated();

    expect($response->json('data.status'))->toBe('draft');
});

it('çözülemez soruyu reddeder', function (): void {
    // Şıklar arasında olmayan doğru cevap: öğrenci ne seçerse yanlış sayılır
    // ve canını kaybeder.
    $response = panel('editor')
        ->postJson('/api/admin/v1/exercises', validExercise([
            'answer_key' => ['correct_option_id' => 'z'],
        ]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'INVALID_EXERCISE_CONTENT');

    expect($response->json('error.details.schema_errors.0'))->toContain('şıklar arasında yok');
});

it('boşluk ve cevap sayısı uyuşmayan soruyu reddeder', function (): void {
    panel('editor')
        ->postJson('/api/admin/v1/exercises', validExercise([
            'type' => 'fill_blank',
            'content' => ['template' => '{{0}} ve {{1}}', 'choices' => ['Töre', 'Kut']],
            'answer_key' => ['blanks' => ['Töre']],
        ]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'INVALID_EXERCISE_CONTENT');
});

it('başka dersin konusuna soru yazılamaz', function (): void {
    // Yanlış konuya yazılan soru, o konunun ustalık istatistiğini bozar.
    $cografyaTopic = Topic::query()->create([
        'subject_id' => DB::table('subjects')->where('code', 'cografya')->value('id'),
        'code' => 'iklim', 'name' => 'İklim', 'sort_order' => 1,
    ]);

    panel('editor')
        ->postJson('/api/admin/v1/exercises', validExercise(['topic_id' => $cografyaTopic->id]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'TOPIC_MISMATCH');
});

it('düzenlemede sürüm artar', function (): void {
    $exercise = Exercise::query()->where('type', 'multiple_choice')->firstOrFail();
    $before = $exercise->version;

    $response = panel('editor')
        ->patchJson("/api/admin/v1/exercises/{$exercise->id}", [
            'explanation' => 'Güncellenmiş açıklama.',
        ])->assertOk();

    expect($response->json('data.version'))->toBe($before + 1);
});

it('cevap anahtarı değişince uyarır', function (): void {
    // Geçmiş istatistikler artık farklı bir soruyu ölçüyor.
    $exercise = Exercise::query()->where('type', 'true_false')->where('status', 'published')->firstOrFail();

    $response = panel('editor')
        ->patchJson("/api/admin/v1/exercises/{$exercise->id}", [
            'answer_key' => ['value' => ! $exercise->answer_key['value']],
        ])->assertOk();

    expect($response->json('data.answer_key_changed'))->toBeTrue()
        ->and($response->json('data.warning'))->toContain('daha önce çözülmüştü');
});

it('geçersiz düzenlemeyi reddeder ve sürümü artırmaz', function (): void {
    $exercise = Exercise::query()->where('type', 'multiple_choice')->firstOrFail();
    $before = $exercise->version;

    panel('editor')
        ->patchJson("/api/admin/v1/exercises/{$exercise->id}", [
            'answer_key' => ['correct_option_id' => 'olmayan-sik'],
        ])->assertStatus(422);

    expect($exercise->fresh()->version)->toBe($before);
});

it('soruyu siler değil ARŞİVLER', function (): void {
    // Yayınlanmış soru geçmiş oturumlarda referanslı; silmek öğrencinin
    // geçmişini ve konu istatistiklerini bozardı.
    $exercise = Exercise::query()->firstOrFail();

    panel('editor')
        ->deleteJson("/api/admin/v1/exercises/{$exercise->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'archived');

    expect(Exercise::query()->find($exercise->id))->not->toBeNull()
        ->and(Exercise::query()->find($exercise->id)->status)->toBe(PublishStatus::Archived);
});

it('denetçi soru düzenleyemez', function (): void {
    panel('denetci')
        ->postJson('/api/admin/v1/exercises', validExercise())
        ->assertStatus(403);
});

it('soru işlemleri denetim kaydına yazılır', function (): void {
    panel('editor')->postJson('/api/admin/v1/exercises', validExercise())->assertCreated();

    $log = DB::table('audit_logs')->latest('id')->first();

    expect($log->action)->toBe('exercise.created')
        ->and($log->entity_type)->toBe('Exercise');
});

it('konu listesi soru formunu besler', function (): void {
    // Soru eklerken topic_id gerekiyor; panel seçenekleri buradan alır.
    $course = Course::query()
        ->where('code', 'tyt_tarih')->firstOrFail();

    $data = panel('editor')
        ->getJson("/api/admin/v1/courses/{$course->id}/topics")
        ->assertOk()
        ->json('data');

    expect($data['topics'])->toHaveCount(3)
        ->and($data['topics'][0])->toHaveKeys(['id', 'code', 'name', 'exercise_count']);
});

it('şablon listesi ünite formunu besler', function (): void {
    $templates = panel('editor')->getJson('/api/admin/v1/unit-templates')->assertOk()->json('data');

    expect($templates)->toHaveCount(2)
        // Editör hangi node'ların üretileceğini önceden görmeli.
        ->and($templates[0]['nodes'])->not->toBeEmpty()
        ->and($templates[0]['nodes'][0])->toHaveKeys(['title', 'type', 'exercise_count']);
});
