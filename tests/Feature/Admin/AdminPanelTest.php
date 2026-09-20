<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enum\SelectionMode;
use App\Modules\Catalog\Domain\Selection\SelectionContext;
use App\Modules\Catalog\Domain\Selection\SelectionRule;
use App\Modules\Catalog\Domain\Selection\SelectorRegistry;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Topic;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Unit;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Shared\Domain\Enum\PublishStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 | Panel yetkilendirmesi ve yayın kapısı.
 |
 | Buradaki testlerin çoğu "kim NE YAPAMAZ" üzerine: yetki hataları sessizdir
 | ve ancak biri yetkisiz bir işi yaptığında fark edilir.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    // Token'lar bir kez alınır; her istekten ÖNCE guard sıfırlanır.
    // Laravel guard'ı çözdüğü kullanıcıyı uygulama örneğinde önbelleğe alır
    // ve testte aynı örnek paylaşıldığı için rol testleri sessizce yeşil geçer.
    $this->tokens = collect([
        'admin' => 'admin@tekrarla.test',
        'editor' => 'editor@tekrarla.test',
        'denetci' => 'denetci@tekrarla.test',
    ])->map(fn (string $email): string => $this->postJson('/api/admin/v1/auth/login', [
        'email' => $email,
        'password' => 'tekrarla-local',
    ])->assertOk()->json('data.token'));
});

/** Verilen rolle istek atar; guard önbelleğini temizler. */
function asAdmin(string $role): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(test()->tokens[$role]);
}

it('yönetici girişi rol ve yetkileri döner', function (): void {
    $response = $this->postJson('/api/admin/v1/auth/login', [
        'email' => 'editor@tekrarla.test',
        'password' => 'tekrarla-local',
    ])->assertOk();

    expect($response->json('data.admin.role'))->toBe('content_editor')
        ->and($response->json('data.admin.abilities.edit_content'))->toBeTrue()
        ->and($response->json('data.admin.abilities.publish_content'))->toBeFalse();
});

it('yanlış şifre ile e-posta varlığını sızdırmaz', function (): void {
    $varOlan = $this->postJson('/api/admin/v1/auth/login', [
        'email' => 'editor@tekrarla.test', 'password' => 'yanlis',
    ])->assertStatus(422)->json('errors.email');

    $olmayan = $this->postJson('/api/admin/v1/auth/login', [
        'email' => 'yok@tekrarla.test', 'password' => 'yanlis',
    ])->assertStatus(422)->json('errors.email');

    expect($varOlan)->toBe($olmayan);
});

it('öğrenci token\'ı panele giremez', function (): void {
    // Ayrı guard ve ayrı tablo olmasının tek sebebi bu.
    $studentToken = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'ogrenci', 'platform' => 'ios',
    ])->json('data.token');

    app('auth')->forgetGuards();

    $this->withToken($studentToken)->getJson('/api/admin/v1/me')->assertUnauthorized();
});

it('yönetici token\'ı öğrenci API\'sine giremez', function (): void {
    asAdmin('admin')->getJson('/api/v1/me')->assertUnauthorized();
});

it('editör ünite şablondan kurabilir', function (): void {
    // Konular DERSE göre seçilmeli: `tyt_tarih` kursunun subject'i Tarih.
    // Rastgele ilk iki konuyu almak başka bir dersin konusunu gönderir ve
    // sunucu bunu TOPIC_MISMATCH ile haklı olarak reddeder.
    $subjectId = Course::query()->where('code', 'tyt_tarih')->value('subject_id');
    $topics = Topic::query()->where('subject_id', $subjectId)->limit(2)->pluck('id')->all();

    $response = asAdmin('editor')
        ->postJson('/api/admin/v1/units', [
            'course_code' => 'tyt_tarih',
            'template_code' => 'standart_unite',
            'title' => 'Tarih ve Zaman',
            'topic_ids' => $topics,
            'sort_order' => 1,
            'grade_level' => 9,
        ])->assertCreated();

    expect($response->json('data.nodes'))->toHaveCount(6)
        ->and($response->json('data.status'))->toBe('draft');
});

it('editör YAYINLAYAMAZ — dört göz ilkesi', function (): void {
    // Soruyu yazan kişinin kendi sorusunu yayınlaması, yanlış bir sorunun
    // doğrudan öğrenciye gitmesi demektir.
    $unit = Unit::query()->firstOrFail();

    asAdmin('editor')
        ->postJson("/api/admin/v1/units/{$unit->id}/publish")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('denetçi yayınlayabilir ama içerik düzenleyemez', function (): void {
    asAdmin('denetci')
        ->postJson('/api/admin/v1/units', [
            'course_code' => 'tyt_tarih',
            'template_code' => 'standart_unite',
            'title' => 'Olmaz',
            'topic_ids' => [1],
        ])->assertStatus(403);

    $unit = Unit::query()->firstOrFail();

    asAdmin('denetci')
        ->postJson("/api/admin/v1/units/{$unit->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');
});

it('ünite başka dersin konusunu alamaz', function (): void {
    // Bir Coğrafya ünitesine Tarih konusu iliştirilirse, havuz kuralı konuya
    // göre çalıştığı için öğrenciye Coğrafya dersinde Tarih sorusu çıkardı.
    // Yayın kapısı bunu yakalayamaz — kural teknik olarak "yeterli soru" bulur.
    $tarihTopic = Topic::query()
        ->whereHas('subject', fn ($q) => $q->where('code', 'tarih'))
        ->firstOrFail();

    asAdmin('editor')
        ->postJson('/api/admin/v1/units', [
            'course_code' => 'tyt_cografya',
            'template_code' => 'standart_unite',
            'title' => 'Karışık Ünite',
            'topic_ids' => [$tarihTopic->id],
        ])->assertStatus(422)
        ->assertJsonPath('error.code', 'TOPIC_MISMATCH');

    expect(Unit::query()->where('title', 'Karışık Ünite')->exists())->toBeFalse();
});

it('yayın kapısı yetersiz havuzu engeller', function (): void {
    // "Şimdilik yayınlayalım, sonra soru ekleriz" yolu bilinçli olarak kapalı.
    $cografya = Course::query()->where('code', 'tyt_cografya')->firstOrFail();

    // Coğrafya'nın kendi konusu var ama havuzunda hiç soru yok.
    $topic = Topic::query()->create([
        'subject_id' => $cografya->subject_id,
        'code' => 'iklim',
        'name' => 'İklim Bilgisi',
        'sort_order' => 1,
    ]);

    $unitId = asAdmin('editor')
        ->postJson('/api/admin/v1/units', [
            'course_code' => 'tyt_cografya',
            'template_code' => 'standart_unite',
            'title' => 'Boş Ünite',
            'topic_ids' => [$topic->id],
        ])->json('data.id');

    $response = asAdmin('denetci')
        ->postJson("/api/admin/v1/units/{$unitId}/publish")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CONTENT_NOT_PUBLISHABLE');

    expect($response->json('error.details.blocking'))->not->toBeEmpty()
        ->and(Unit::query()->find($unitId)->status)->toBe(PublishStatus::Draft);
});

it('kural önizlemesi kaç soru geldiğini söyler', function (): void {
    $node = UnitNode::query()->firstOrFail();

    $response = asAdmin('editor')
        ->getJson("/api/admin/v1/nodes/{$node->id}/preview-selection")
        ->assertOk();

    expect($response->json('data.required'))->toBe($node->exercise_count)
        ->and($response->json('data.passes'))->toBeTrue();
});

it('ünite node keşfi yalnız doğru ünitenin sıralı node listesini döner', function (): void {
    $unit = Unit::query()->with('nodes')->firstOrFail();
    $other = Unit::query()->whereKeyNot($unit->id)->with('nodes')->firstOrFail();

    $response = asAdmin('editor')
        ->getJson("/api/admin/v1/units/{$unit->id}/nodes")
        ->assertOk()
        ->assertJsonPath('data.unit.id', $unit->id);

    $ids = collect($response->json('data.nodes'))->pluck('id');
    expect($ids->all())->toBe($unit->nodes->sortBy('sort_order')->pluck('id')->values()->all())
        ->and($ids->intersect($other->nodes->pluck('id')))->toBeEmpty();

    app('auth')->forgetGuards();
    $this->withHeaders(['Authorization' => ''])
        ->getJson("/api/admin/v1/units/{$unit->id}/nodes")
        ->assertUnauthorized();
});

it('yayın önizlemesi üniteye ait taslak fixed-list sorusunu görür ama runtime görmez', function (): void {
    $unit = Unit::query()->with('course')->firstOrFail();
    $node = $unit->nodes()->firstOrFail();
    $draft = Exercise::query()->firstOrFail()->replicate(['uuid']);
    $draft->owner_unit_id = $unit->id;
    $draft->status = PublishStatus::Draft;
    $draft->save();

    $node->update([
        'exercise_count' => 1,
        'selection_rule' => ['mode' => 'fixed', 'count' => 1, 'exercise_ids' => [$draft->id]],
    ]);

    asAdmin('editor')->getJson("/api/admin/v1/nodes/{$node->id}/preview-selection")
        ->assertOk()
        ->assertJsonPath('data.available', 1)
        ->assertJsonPath('data.passes', true);

    $rule = SelectionRule::fromArray($node->fresh()->selection_rule);
    $runtime = app(SelectorRegistry::class)->for(SelectionMode::Fixed)->select($rule, new SelectionContext(
        unitId: $unit->id,
        unitTopicIds: $unit->topics()->pluck('topics.id')->map(fn ($id): int => (int) $id)->all(),
        courseScope: $unit->course->scope->value,
    ));

    expect($runtime->exercises)->toBeEmpty()
        ->and($draft->fresh()->status)->toBe(PublishStatus::Draft);
});

it('yayın önizlemesi üniteye ait taslak pool sorularını görür ve kalıcı durum değiştirmez', function (): void {
    $unit = Unit::query()->with('course')->firstOrFail();
    $node = $unit->nodes()->firstOrFail();
    $draft = Exercise::query()->where('owner_unit_id', $unit->id)->firstOrFail();
    $draft->update(['status' => PublishStatus::Draft]);

    $node->update([
        'exercise_count' => 1,
        'selection_rule' => [
            'mode' => 'pool', 'count' => 1,
            'filters' => [
                'topics' => [$draft->topic_id],
                'difficulty' => ['min' => $draft->difficulty, 'max' => $draft->difficulty],
                'types' => [$draft->type->value],
            ],
            'fallback' => 'none',
        ],
    ]);

    asAdmin('editor')->getJson("/api/admin/v1/nodes/{$node->id}/preview-selection")
        ->assertOk()
        ->assertJsonPath('data.passes', true);

    expect($draft->fresh()->status)->toBe(PublishStatus::Draft);
});

it('başarısız yayın hiçbir durumu kısmen değiştirmez', function (): void {
    $unit = Unit::query()->firstOrFail();
    $unit->update(['status' => PublishStatus::Review]);
    $unit->nodes()->update([
        'status' => PublishStatus::Review,
        'exercise_count' => 1,
        'selection_rule' => ['mode' => 'fixed', 'count' => 1, 'exercise_ids' => [999999999]],
    ]);
    $exercise = Exercise::query()->where('owner_unit_id', $unit->id)->firstOrFail();
    $exercise->update(['status' => PublishStatus::Review]);

    asAdmin('denetci')->postJson("/api/admin/v1/units/{$unit->id}/publish")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'CONTENT_NOT_PUBLISHABLE');

    expect($unit->fresh()->status)->toBe(PublishStatus::Review)
        ->and($unit->nodes()->where('status', 'review')->count())->toBe($unit->nodes()->count())
        ->and($exercise->fresh()->status)->toBe(PublishStatus::Review);
});

it('başarılı yayın taslak ve incelemedeki soruları yayınlar, arşivleneni korur', function (): void {
    $unit = Unit::query()->firstOrFail();
    $draft = Exercise::query()->where('owner_unit_id', $unit->id)->firstOrFail();
    $draft->update(['status' => PublishStatus::Draft]);
    $review = Exercise::query()->where('owner_unit_id', $unit->id)->whereKeyNot($draft->id)->firstOrFail();
    $review->update(['status' => PublishStatus::Review]);
    $archived = Exercise::query()->where('owner_unit_id', $unit->id)
        ->whereNotIn('id', [$draft->id, $review->id])->firstOrFail();
    $archived->update(['status' => PublishStatus::Archived]);

    $unit->update(['status' => PublishStatus::Review]);
    $unit->nodes()->update([
        'status' => PublishStatus::Review,
        'exercise_count' => 2,
        'selection_rule' => [
            'mode' => 'fixed', 'count' => 2, 'exercise_ids' => [$draft->id, $review->id],
        ],
    ]);

    asAdmin('denetci')->postJson("/api/admin/v1/units/{$unit->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect($unit->fresh()->status)->toBe(PublishStatus::Published)
        ->and($unit->nodes()->where('status', 'published')->count())->toBe($unit->nodes()->count())
        ->and($draft->fresh()->status)->toBe(PublishStatus::Published)
        ->and($review->fresh()->status)->toBe(PublishStatus::Published)
        ->and($archived->fresh()->status)->toBe(PublishStatus::Archived);
});

it('yayın önizlemesi izin verilen global yayınlanmış soruyu sayar', function (): void {
    $unit = Unit::query()->firstOrFail();
    $node = $unit->nodes()->firstOrFail();
    $global = Exercise::query()->where('status', PublishStatus::Published)->firstOrFail();
    $global->update(['owner_unit_id' => null]);
    $node->update([
        'exercise_count' => 1,
        'selection_rule' => ['mode' => 'fixed', 'count' => 1, 'exercise_ids' => [$global->id]],
    ]);

    asAdmin('editor')->getJson("/api/admin/v1/nodes/{$node->id}/preview-selection")
        ->assertOk()
        ->assertJsonPath('data.passes', true);
});

it('yalnızca süper yönetici müfredat eşlemesini değiştirebilir', function (): void {
    $variantId = DB::table('exam_variants')->where('code', 'yks_say')->value('id');

    asAdmin('editor')
        ->getJson("/api/admin/v1/exam-variants/{$variantId}/courses")
        ->assertStatus(403);

    asAdmin('admin')
        ->getJson("/api/admin/v1/exam-variants/{$variantId}/courses")
        ->assertOk()
        ->assertJsonPath('data.exam_variant.code', 'yks_say');
});

it('müfredat seçenekleri source-driven variant ve section kimliklerini döner', function (): void {
    asAdmin('editor')->getJson('/api/admin/v1/curriculum/options')->assertForbidden();
    app('auth')->forgetGuards();
    $this->withHeaders(['Authorization' => ''])
        ->getJson('/api/admin/v1/curriculum/options')
        ->assertUnauthorized();

    $response = asAdmin('admin')->getJson('/api/admin/v1/curriculum/options')->assertOk();
    $variant = DB::table('exam_variants')->orderBy('exam_id')->orderBy('sort_order')->first();
    $section = DB::table('exam_sections')->orderBy('exam_id')->orderBy('sort_order')->first();

    expect($response->json('data.variants.0.id'))->toBe($variant->id)
        ->and($response->json('data.variants.0.code'))->toBe($variant->code)
        ->and($response->json('data.variants.0.name'))->toBe($variant->name)
        ->and($response->json('data.sections.0.id'))->toBe($section->id)
        ->and($response->json('data.sections.0.exam_id'))->toBe($section->exam_id)
        ->and($response->json('data.sections.0.code'))->toBe($section->code);
});

it('müfredat eşlemesi panelden düzenlenebilir', function (): void {
    // Ders sisteminin temel iddiası: bu bir veri kararıdır, deploy değil.
    $variantId = DB::table('exam_variants')->where('code', 'yks_say')->value('id');

    $current = asAdmin('admin')
        ->getJson("/api/admin/v1/exam-variants/{$variantId}/courses")
        ->json('data.courses');

    // Din Kültürü'nü ücretsize çevir ve listeden bir ders çıkar.
    $updated = collect($current)
        ->reject(fn (array $c): bool => $c['code'] === 'tyt_felsefe')
        ->map(fn (array $c): array => [
            'course_id' => $c['course_id'],
            'exam_section_id' => $c['exam_section_id'],
            'sort_order' => $c['sort_order'],
            'access' => $c['code'] === 'tyt_din' ? 'free' : $c['access'],
            'exam_weight' => $c['exam_weight'],
        ])->values()->all();

    asAdmin('admin')
        ->putJson("/api/admin/v1/exam-variants/{$variantId}/courses", ['courses' => $updated])
        ->assertOk()
        ->assertJsonPath('data.course_count', count($updated));

    // Öğrenci tarafında anında yansır — kod değişmeden.
    $studentToken = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'mufredat-testi', 'platform' => 'ios',
    ])->json('data.token');

    app('auth')->forgetGuards();
    $this->withToken($studentToken)->postJson('/api/v1/onboarding', ['exam_code' => 'yks', 'field' => 'say']);

    $courses = collect($this->withToken($studentToken)->getJson('/api/v1/me/courses')->json('data.sections'))
        ->flatMap(fn (array $s): array => $s['courses']);

    expect($courses->firstWhere('code', 'tyt_felsefe'))->toBeNull()
        ->and($courses->firstWhere('code', 'tyt_din')['access'])->toBe('free');
});

it('yönetici eylemleri denetim kaydına yazılır', function (): void {
    $subjectId = Course::query()->where('code', 'tyt_tarih')->value('subject_id');
    $topics = Topic::query()->where('subject_id', $subjectId)->limit(1)->pluck('id')->all();

    asAdmin('editor')
        ->postJson('/api/admin/v1/units', [
            'course_code' => 'tyt_tarih',
            'template_code' => 'hafif_unite',
            'title' => 'Denetim Testi',
            'topic_ids' => $topics,
        ])->assertCreated();

    $log = DB::table('audit_logs')->latest('id')->first();

    expect($log->action)->toBe('unit.created')
        ->and($log->entity_type)->toBe('Unit')
        ->and($log->admin_user_id)->not->toBeNull();
});

it('içerik paketi panelden içe aktarılabilir', function (): void {
    $package = json_decode(file_get_contents(database_path('content/ayt_matematik_turev.json')), true);
    $package['unit']['title'] = 'Türev (panelden)';

    $response = asAdmin('editor')
        ->postJson('/api/admin/v1/content/import', $package)
        ->assertCreated();

    expect($response->json('data.exercises'))->toBeGreaterThan(0)
        ->and($response->json('data.nodes'))->toBe(4);
});

it('ders ağacı panelin sol menüsünü besler', function (): void {
    $response = asAdmin('editor')
        ->getJson('/api/admin/v1/courses')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(21)
        ->and(collect($response->json('data'))->firstWhere('code', 'tyt_tarih')['unit_count'])->toBe(1);
});

it('kimliksiz panel isteği reddedilir', function (): void {
    $this->getJson('/api/admin/v1/courses')->assertUnauthorized();
});
