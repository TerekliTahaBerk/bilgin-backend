<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enum\DifficultyLevel;
use App\Modules\Catalog\Domain\Enum\NodeType;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Topic;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Unit;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Shared\Domain\Enum\PublishStatus;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 | Panelin KEŞİF uçları.
 |
 | Panel, ünitenin adımlarını ve müfredat varyantlarını kimlikleriyle
 | öğrenemezse geriye tek seçenek kalır: kimlikleri panele gömmek. Gömülü
 | kimlik, ortam değiştiğinde yanlış üniteyi okumak ya da yanlış varyantın
 | müfredatını ezmekle biter — ikisi de sessizdir. Bu uçlar o yolu kapatır.
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

function discoveryPanel(string $role): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(test()->tokens[$role]);
}

/** Şablondan kurulmuş, dört adımlı taze bir taslak ünite. */
function discoveryUnit(): Unit
{
    $course = Course::query()->where('code', 'tyt_cografya')->firstOrFail();

    $topic = Topic::query()->create([
        'subject_id' => $course->subject_id,
        'code' => 'kesif_iklim',
        'name' => 'İklim Bilgisi',
        'sort_order' => 1,
    ]);

    $unitId = discoveryPanel('editor')
        ->postJson('/api/admin/v1/units', [
            'course_code' => 'tyt_cografya',
            'template_code' => 'hafif_unite',
            'title' => 'Keşif Ünitesi',
            'topic_ids' => [$topic->id],
        ])->assertCreated()->json('data.id');

    return Unit::query()->findOrFail($unitId);
}

it('ünite adımları kimlikleri ve ünite künyesiyle döner', function (): void {
    $unit = discoveryUnit();

    $response = discoveryPanel('editor')
        ->getJson("/api/admin/v1/units/{$unit->id}/nodes")
        ->assertOk()
        ->assertJsonPath('data.unit.id', $unit->id)
        ->assertJsonPath('data.unit.title', 'Keşif Ünitesi')
        ->assertJsonPath('data.unit.status', PublishStatus::Draft->value);

    $nodes = $response->json('data.nodes');

    expect($nodes)->toHaveCount(4)
        ->and($nodes[0])->toHaveKeys([
            'id', 'title', 'type', 'difficulty', 'sort_order', 'exercise_count', 'status',
        ]);
});

it('ünite adımları sort_order sırasıyla gelir', function (): void {
    $unit = discoveryUnit();

    // Sıra alanını bilerek bozup uca sıralamayı yaptırıyoruz: sıra
    // eklenme sırasına denk gelirse test hiçbir şey kanıtlamazdı.
    $ids = $unit->nodes()->orderBy('sort_order')->pluck('id')->all();
    UnitNode::query()->whereKey($ids[0])->update(['sort_order' => 99]);

    $nodes = discoveryPanel('editor')
        ->getJson("/api/admin/v1/units/{$unit->id}/nodes")
        ->assertOk()
        ->json('data.nodes');

    $sortOrders = array_column($nodes, 'sort_order');
    $sorted = $sortOrders;
    sort($sorted);

    expect($sortOrders)->toBe($sorted)
        ->and(end($nodes)['id'])->toBe($ids[0]);
});

it('ünite adımları yalnızca istenen üniteden gelir', function (): void {
    $unit = discoveryUnit();

    $nodes = discoveryPanel('editor')
        ->getJson("/api/admin/v1/units/{$unit->id}/nodes")
        ->assertOk()
        ->json('data.nodes');

    $yabanci = UnitNode::query()->where('unit_id', '!=', $unit->id)->pluck('id')->all();

    expect($yabanci)->not->toBeEmpty()
        ->and(array_intersect(array_column($nodes, 'id'), $yabanci))->toBe([]);
});

it('ünite adımlarının enum alanları backend değerleriyle serileşir', function (): void {
    $unit = discoveryUnit();

    $nodes = discoveryPanel('editor')
        ->getJson("/api/admin/v1/units/{$unit->id}/nodes")
        ->assertOk()
        ->json('data.nodes');

    $tipler = array_map(fn (NodeType $t): string => $t->value, NodeType::cases());
    $zorluklar = array_map(fn (DifficultyLevel $d): string => $d->value, DifficultyLevel::cases());
    $durumlar = array_map(fn (PublishStatus $s): string => $s->value, PublishStatus::cases());

    foreach ($nodes as $node) {
        expect($node['type'])->toBeIn($tipler)
            ->and($node['difficulty'])->toBeIn($zorluklar)
            ->and($node['status'])->toBeIn($durumlar);
    }

    // Şablon 'study' ve 'mini_challenge' üretiyor; değerler ham enum karşılığı.
    expect(array_column($nodes, 'type'))->toContain(NodeType::Study->value)
        ->and(array_column($nodes, 'type'))->toContain(NodeType::MiniChallenge->value);
});

it('ünite adımları iç kural JSON alanlarını sızdırmaz', function (): void {
    $unit = discoveryUnit();

    $nodes = discoveryPanel('editor')
        ->getJson("/api/admin/v1/units/{$unit->id}/nodes")
        ->assertOk()
        ->json('data.nodes');

    foreach ($nodes as $node) {
        expect($node)->not->toHaveKey('selection_rule')
            ->and($node)->not->toHaveKey('unlock_rule');
    }
});

it('adımsız ünite boş dizi döner', function (): void {
    $course = Course::query()->where('code', 'tyt_cografya')->firstOrFail();

    $unit = Unit::query()->create([
        'course_id' => $course->id,
        'title' => 'Adımsız Ünite',
        'sort_order' => 99,
        'access' => 'free',
        'status' => PublishStatus::Draft,
    ]);

    discoveryPanel('editor')
        ->getJson("/api/admin/v1/units/{$unit->id}/nodes")
        ->assertOk()
        ->assertJsonPath('data.nodes', []);
});

it('ünite adımlarını içerik yetkisi olan her yönetici okuyabilir', function (): void {
    $unit = discoveryUnit();

    // Bu bir OKUMA ucu: yayın ya da müfredat yetkisi istemek, denetçinin
    // hazırlık ekranını hiç görememesi demek olurdu.
    foreach (['admin', 'editor', 'denetci'] as $role) {
        discoveryPanel($role)
            ->getJson("/api/admin/v1/units/{$unit->id}/nodes")
            ->assertOk();
    }
});

it('ünite adımları kimliksiz istekte 401 döner', function (): void {
    $unit = Unit::query()->firstOrFail();

    app('auth')->forgetGuards();

    $this->getJson("/api/admin/v1/units/{$unit->id}/nodes")->assertUnauthorized();
});

it('müfredat seçenekleri varyant ve oturumları kimlikleriyle döner', function (): void {
    $response = discoveryPanel('admin')
        ->getJson('/api/admin/v1/curriculum/options')
        ->assertOk();

    $variants = $response->json('data.variants');
    $sections = $response->json('data.sections');

    $say = collect($variants)->firstWhere('code', 'yks_say');

    expect($say)->not->toBeNull()
        ->and($say['id'])->toBe(DB::table('exam_variants')->where('code', 'yks_say')->value('id'))
        ->and($say)->toHaveKeys(['id', 'exam_id', 'code', 'name', 'field_code', 'sort_order', 'is_active'])
        ->and($say['name'])->not->toBeEmpty()
        ->and($say['is_active'])->toBeBool();

    foreach ($sections as $section) {
        expect($section)->toHaveKeys(['id', 'exam_id', 'code', 'name', 'sort_order'])
            ->and($section['exam_id'])->toBeInt();
    }

    // Panel oturumları varyantın exam_id'siyle süzüyor; eşleşme tutmalı.
    expect(collect($sections)->where('exam_id', $say['exam_id'])->pluck('code'))->toContain('tyt');
});

it('müfredat seçenekleri kararlı sırada gelir', function (): void {
    $ilk = discoveryPanel('admin')->getJson('/api/admin/v1/curriculum/options')->assertOk()->json('data');
    $ikinci = discoveryPanel('admin')->getJson('/api/admin/v1/curriculum/options')->assertOk()->json('data');

    expect($ikinci)->toBe($ilk);

    foreach (['variants', 'sections'] as $key) {
        $anahtarlar = array_map(
            static fn (array $row): array => [$row['exam_id'], $row['sort_order'], $row['id']],
            $ilk[$key],
        );
        $sirali = $anahtarlar;
        sort($sirali);

        expect($anahtarlar)->toBe($sirali);
    }
});

it('müfredat seçenekleri içerik yetkisiyle okunamaz', function (): void {
    discoveryPanel('editor')
        ->getJson('/api/admin/v1/curriculum/options')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

it('müfredat seçenekleri kimliksiz istekte 401 döner', function (): void {
    app('auth')->forgetGuards();

    $this->getJson('/api/admin/v1/curriculum/options')->assertUnauthorized();
});
