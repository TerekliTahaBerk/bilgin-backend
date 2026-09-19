<?php

declare(strict_types=1);

use App\Modules\Admin\Domain\Enum\AdminRole;
use App\Modules\Admin\Infrastructure\Eloquent\Model\AdminUser;
use App\Modules\Catalog\Application\UseCase\ImportContentPackage;
use App\Modules\Catalog\Database\Seeders\SubjectSeeder;
use App\Modules\Catalog\Database\Seeders\TopicSeeder;
use App\Modules\Catalog\Database\Seeders\UnitTemplateSeeder;
use App\Modules\Catalog\Database\Seeders\YksCourseSeeder;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Unit;
use App\Modules\Curriculum\Database\Seeders\YksExamSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 | database/content altındaki her paket, üretime gitmeden önce burada
 | sınanıyor. İçerik hatası pahalıdır: yayın kapısından geçmeyen bir ünite
 | öğrenciye boş tur gösterir, yanlış konu kodu ustalık takibini böler.
 */

beforeEach(function (): void {
    $this->seed(YksExamSeeder::class);
    $this->seed(SubjectSeeder::class);
    $this->seed(TopicSeeder::class);
    $this->seed(YksCourseSeeder::class);
    $this->seed(UnitTemplateSeeder::class);

    // Yayınlama süper yönetici istiyor (dört göz ilkesi: editör yayınlayamaz).
    $admin = AdminUser::query()->create([
        'name' => 'İçerik Testi',
        'email' => 'icerik@test.local',
        'password' => 'cok-gizli-sifre',
        'role' => AdminRole::SuperAdmin,
        'is_active' => true,
    ]);

    $this->adminToken = $admin->createToken('test', ['admin'])->plainTextToken;
});

/** Yayın isteği için oturum açmış süper yönetici. */
function asPublisher(): TestCase
{
    app('auth')->forgetGuards();

    return test()->withToken(test()->adminToken);
}

/** @return list<array{name: string, package: array<string, mixed>}> */
function contentPackages(): array
{
    $packages = [];

    foreach (glob(database_path('content/*.json')) ?: [] as $path) {
        $packages[] = [
            'name' => basename($path),
            'package' => json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    return $packages;
}

it('içerik klasöründe en az bir paket var', function (): void {
    expect(contentPackages())->not->toBeEmpty();
});

it('paketlerdeki konu kodları TopicSeeder ile örtüşür', function (): void {
    // Uyuşmayan kod, aynı konunun ikinci kez açılması demek: öğrencinin
    // o konudaki geçmişi ikiye bölünür ve konu analizi yanlış sonuç verir.
    foreach (contentPackages() as ['name' => $name, 'package' => $package]) {
        $subjectId = DB::table('subjects')->where('code', $package['subject'])->value('id');

        expect($subjectId)->not->toBeNull("{$name}: '{$package['subject']}' dersi yok");

        $known = DB::table('topics')->where('subject_id', $subjectId)->pluck('code')->all();

        foreach ($package['topics'] as $topic) {
            // toContain değil: o, ek argümanları ikinci bir aranan değer
            // sayıyor ve mesaj arama ölçütüne dönüşüyor.
            expect(in_array($topic['code'], $known, true))->toBeTrue(
                "{$name}: '{$topic['code']}' TopicSeeder'da yok ({$topic['name']})",
            );
        }
    }
});

it('her paket içe aktarılabilir ve ünitesi YAYINLANABİLİR', function (): void {
    // Asıl sınav bu: yayın kapısı, node'larının hepsi yeterli soru
    // getirmeyen üniteyi reddediyor. "Sonra soru ekleriz" yolu kapalı.
    foreach (contentPackages() as ['name' => $name, 'package' => $package]) {
        $report = app(ImportContentPackage::class)($package);

        expect($report->exerciseCount)->toBe(
            count($package['exercises']),
            "{$name}: paketteki soru sayısı ile içe aktarılan tutmuyor",
        );

        $unit = Unit::query()->findOrFail($report->unitId);

        asPublisher()
            ->postJson("/api/admin/v1/units/{$unit->id}/publish")
            ->assertOk(); // yayın kapısını geçemezse burada patlar
    }
});

it('aynı paket iki kez aktarılınca soru çoğalmaz', function (): void {
    $package = contentPackages()[0]['package'];

    $first = app(ImportContentPackage::class)($package);
    $before = DB::table('exercises')->count();

    $second = app(ImportContentPackage::class)($package);

    expect(DB::table('exercises')->count())->toBe($before)
        ->and($second->unitId)->toBe($first->unitId);
});

it('her sorunun açıklaması ya da kendi kendine değerlendirmesi var', function (): void {
    // Yanlış cevabın doğrusunu görmeden geçmek öğrenmeyi yarıda bırakır.
    // flashcard istisna: orada değerlendirme öğrencinin kendisinde.
    foreach (contentPackages() as ['name' => $name, 'package' => $package]) {
        foreach ($package['exercises'] as $index => $exercise) {
            if (in_array($exercise['type'], ['flashcard', 'word_order'], true)) {
                continue;
            }

            expect($exercise['explanation'] ?? null)->not->toBeEmpty(
                "{$name}: #{$index} ({$exercise['type']}) açıklamasız",
            );
        }
    }
});
