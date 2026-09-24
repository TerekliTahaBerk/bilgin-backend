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

it('bozuk içerik İÇE AKTARILMAZ', function (): void {
    // Panelden soru eklerken şema doğrulanıyordu ama içe aktarma bu kapıyı
    // atlıyordu: aynı veri farklı kapıdan girince denetimsiz kalıyordu.
    // Bozuk bir soru öğrenciye ancak tur ortasında görünür.
    $package = contentPackages()[0]['package'];

    $package['exercises'] = [
        [
            'topic' => $package['topics'][0]['code'],
            'type' => 'multiple_choice',
            'difficulty' => 2,
            'scopes' => ['tyt'],
            // Doğru şık listede YOK.
            'content' => ['stem' => 'Bozuk soru', 'options' => [
                ['id' => 'a', 'text' => 'A'],
                ['id' => 'b', 'text' => 'B'],
            ]],
            'answer_key' => ['correct_option_id' => 'z'],
            'explanation' => 'x',
        ],
    ];

    expect(fn () => app(ImportContentPackage::class)($package))
        ->toThrow(RuntimeException::class);
});

it('bozuk paket HİÇBİR ŞEY yazmaz', function (): void {
    // Doğrulama yazmadan ÖNCE çalışıyor: yarım aktarılmış bir paket,
    // ünitesi açılmış ama soruları eksik bir içerik bırakırdı.
    $package = contentPackages()[0]['package'];
    $package['unit']['title'] = 'Yazılmaması Gereken Ünite';
    $package['exercises'][0]['answer_key'] = [];  // tipe göre geçersiz

    $unitsBefore = DB::table('units')->count();
    $exercisesBefore = DB::table('exercises')->count();

    try {
        app(ImportContentPackage::class)($package);
    } catch (RuntimeException) {
        // beklenen
    }

    expect(DB::table('units')->count())->toBe($unitsBefore)
        ->and(DB::table('exercises')->count())->toBe($exercisesBefore)
        ->and(DB::table('units')->where('title', 'Yazılmaması Gereken Ünite')->exists())
        ->toBeFalse();
});

it('doğrulama hatası TÜM sorunları tek seferde listeler', function (): void {
    // 40 soruluk bir pakette teker teker hata almak, içerik yazanı kırk
    // tur döndürür.
    $package = contentPackages()[0]['package'];
    $topic = $package['topics'][0]['code'];

    $package['exercises'] = [
        [
            'topic' => $topic, 'type' => 'true_false', 'difficulty' => 1,
            'scopes' => ['tyt'],
            'content' => ['statement' => 'Bir ifade'],
            'answer_key' => ['value' => 'true'],   // dize, boolean değil
            'explanation' => 'x',
        ],
        [
            'topic' => $topic, 'type' => 'fill_blank', 'difficulty' => 2,
            'scopes' => ['tyt'],
            'content' => ['template' => 'Boşluksuz şablon', 'choices' => ['A', 'B']],
            'answer_key' => ['blanks' => ['A']],   // şablonda {{0}} yok
            'explanation' => 'x',
        ],
    ];

    try {
        app(ImportContentPackage::class)($package);
        expect(false)->toBeTrue('doğrulama hatası bekleniyordu');
    } catch (RuntimeException $e) {
        // İki sorunun da mesajda geçmesi gerekiyor.
        expect($e->getMessage())->toContain('#0')
            ->and($e->getMessage())->toContain('#1');
    }
});
