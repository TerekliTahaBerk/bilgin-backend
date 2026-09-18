<?php

declare(strict_types=1);

use App\Console\Commands\ProvisionCommand;
use App\Modules\Catalog\Database\Seeders\TopicSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Provision'ın yapı verisi kapısı.
 |
 | Eskiden tek bir kapı vardı: "exams doluysa hiçbir seeder'ı çalıştırma".
 | Bu, sonradan eklenen bir seeder'ın var olan kurulumlarda ASLA
 | çalışmaması demekti — konu listesi tam bu yüzden üretime inememişti.
 */

/** @return array<class-string, string> */
function structureSeeders(): array
{
    $property = new ReflectionClassConstant(ProvisionCommand::class, 'STRUCTURE_SEEDERS');

    /** @var array<class-string, string> */
    return $property->getValue();
}

it('her seeder gerçek bir tabloya işaret eder', function (): void {
    // Yazım hatası olan bir tablo adı sessizce "tablo yok, atlandı" derdi
    // ve o seeder hiç çalışmazdı.
    foreach (structureSeeders() as $seeder => $table) {
        expect(Schema::hasTable($table))
            ->toBeTrue("{$seeder} → '{$table}' tablosu yok");
    }
});

it('her seeder sınıfı var', function (): void {
    foreach (structureSeeders() as $seeder => $table) {
        expect(class_exists($seeder))->toBeTrue("{$seeder} bulunamadı");
    }
});

it('boş kalan tablo sonraki açılışta doldurulur', function (): void {
    // Asıl düzeltme bu: diğer tablolar doluyken yeni bir seeder çalışmalı.
    $this->seed(DatabaseSeeder::class);

    DB::table('unit_topics')->delete();
    DB::table('topics')->delete();

    expect(DB::table('exams')->exists())->toBeTrue()
        ->and(DB::table('topics')->exists())->toBeFalse();

    $this->artisan('db:seed', [
        '--class' => TopicSeeder::class,
        '--force' => true,
    ])->assertSuccessful();

    expect(DB::table('topics')->exists())->toBeTrue();
});
