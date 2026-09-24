<?php

declare(strict_types=1);

use App\Modules\Gamification\Console\SeedDemoDataCommand;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/*
 | Demo verisi, uygulamayı DOLU görmek için. Boş bir kurulumda lig tek
 | kişilik, profil sıfır — ekranların yarısı gerçekte nasıl görüneceğini
 | göstermiyor.
 */

beforeEach(fn () => test()->seed(DatabaseSeeder::class));

it('sahte öğrencileri lig tablosuna sokar', function (): void {
    $this->artisan('demo:seed', ['--students' => 6])->assertSuccessful();

    // XP gerçek olay üzerinden verildiği için lig üyeliğini her zamanki
    // dinleyici kurmalı — elle satır yazsaydık bu ilişki hiç oluşmazdı.
    expect(DB::table('league_memberships')->count())->toBeGreaterThanOrEqual(6);
});

it('XP dağılımı sıralamayı anlamlı kılar', function (): void {
    $this->artisan('demo:seed', ['--students' => 8])->assertSuccessful();

    $xp = DB::table('league_memberships')->pluck('weekly_xp');

    // Hepsi aynı olsaydı yükselme/düşme bölgeleri ayırt edilemezdi.
    expect($xp->unique()->count())->toBeGreaterThan(1)
        ->and($xp->min())->toBeGreaterThan(0);
});

it('öğrencilerin adı var', function (): void {
    $this->artisan('demo:seed', ['--students' => 4])->assertSuccessful();

    // Adsız kullanıcı ligde "Öğrenci" görünür ve tablo kişisizleşir.
    $names = DB::table('users')
        ->whereIn('id', demoUserIds())
        ->pluck('name');

    expect($names->filter())->toHaveCount(4);
});

it('iki kez çalıştırmak XP çoğaltmaz', function (): void {
    $this->artisan('demo:seed', ['--students' => 5])->assertSuccessful();
    $before = DB::table('xp_ledger')->where('source_type', 'demo_seed')->sum('amount');

    $this->artisan('demo:seed', ['--students' => 5])->assertSuccessful();

    // XP defteri unique(source_type, source_id) tutuyor; komut idempotent
    // olmalı yoksa her çalıştırmada sıralama şişer.
    expect(DB::table('xp_ledger')->where('source_type', 'demo_seed')->sum('amount'))
        ->toBe($before);
});

it('demo:clear ürettiğini geri alır', function (): void {
    $this->artisan('demo:seed', ['--students' => 5])->assertSuccessful();
    expect(demoUserIds())->toHaveCount(5);

    $this->artisan('demo:clear', ['--force' => true])->assertSuccessful();

    // Geri alınabilir olması şart: aksi hâlde sahte kullanıcılar üretim
    // istatistiklerine kalıcı karışır.
    expect(demoUserIds())->toBeEmpty()
        ->and(DB::table('league_memberships')->count())->toBe(0);
});

it('üretimde --force olmadan çalışmaz', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('demo:seed', ['--students' => 3])->assertFailed();

    expect(demoUserIds())->toBeEmpty();
});

/** @return list<int> */
function demoUserIds(): array
{
    return DB::table('devices')
        ->where('device_identifier', 'like', SeedDemoDataCommand::DEVICE_PREFIX.'%')
        ->pluck('user_id')
        ->unique()
        ->values()
        ->all();
}
