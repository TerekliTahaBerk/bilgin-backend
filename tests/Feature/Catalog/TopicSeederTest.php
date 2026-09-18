<?php

declare(strict_types=1);

use App\Modules\Catalog\Database\Seeders\SubjectSeeder;
use App\Modules\Catalog\Database\Seeders\TopicSeeder;
use Illuminate\Support\Facades\DB;

/*
 | Konular müfredat yapısı; panel bunlar olmadan tek soru bile kaydedemiyor.
 */

beforeEach(function (): void {
    $this->seed(SubjectSeeder::class);
    $this->seed(TopicSeeder::class);
});

it('her ders için konu yükler', function (): void {
    $counts = DB::table('topics')
        ->join('subjects', 'subjects.id', '=', 'topics.subject_id')
        ->selectRaw('subjects.code as subject_code, count(*) as total')
        ->groupBy('subjects.code')
        ->pluck('total', 'subject_code');

    // Onbir dersin hepsinde konu olmalı; biri boş kalırsa o dersin
    // içeriği hiç girilemez.
    expect($counts)->toHaveCount(11)
        ->and($counts->min())->toBeGreaterThan(9);
});

it('ikinci çalıştırmada konu çoğaltmaz', function (): void {
    $before = DB::table('topics')->count();

    $this->seed(TopicSeeder::class);

    expect(DB::table('topics')->count())->toBe($before);
});

it('ikinci çalıştırmada kimlikleri korur', function (): void {
    // Kimlikler değişirse soru ve üniteler yetim kalır.
    $before = DB::table('topics')->orderBy('id')->pluck('code', 'id');

    $this->seed(TopicSeeder::class);

    expect(DB::table('topics')->orderBy('id')->pluck('code', 'id')->all())
        ->toBe($before->all());
});

it('Türkçe karakterleri kararlı biçimde koda çevirir', function (): void {
    $codes = DB::table('topics')->pluck('code');

    // Kod, upsert'ün kimlik anahtarı: içinde Türkçe karakter ya da boşluk
    // kalırsa yerel ayara göre değişip aynı konuyu ikinci kez ekleyebilir.
    expect($codes->filter(fn (string $c): bool => preg_match('/^[a-z0-9_]+$/', $c) !== 1))
        ->toBeEmpty();
});

it('bilinen konular beklenen kodla gelir', function (): void {
    $codes = DB::table('topics')->pluck('code', 'name');

    expect($codes['Sözcükte Anlam'])->toBe('sozcukte_anlam')
        ->and($codes['Üçgende Benzerlik'])->toBe('ucgende_benzerlik')
        ->and($codes['İlk Türk Devletleri'])->toBe('ilk_turk_devletleri');
});

it('konular ders seviyesinde tutulur, kursa bağlı değildir', function (): void {
    // "Türev" tek kayıt olmalı: TYT ve AYT Matematik aynı konuyu paylaşıyor.
    // İkiye bölünürse öğrencinin ustalığı da bölünür.
    expect(DB::table('topics')->where('name', 'Türev')->count())->toBe(1);
});

it('konular öğretim sırasına göre dizilir', function (): void {
    $matematik = DB::table('subjects')->where('code', 'matematik')->value('id');

    $first = DB::table('topics')
        ->where('subject_id', $matematik)
        ->orderBy('sort_order')
        ->value('name');

    expect($first)->toBe('Temel Kavramlar');
});
