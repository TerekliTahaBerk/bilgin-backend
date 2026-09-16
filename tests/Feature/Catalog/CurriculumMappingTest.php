<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamVariant;
use Database\Seeders\DatabaseSeeder;

/*
 | Ders sisteminin can alıcı iddiası: aynı ders birden fazla alanda görünür
 | ama TEK KAYITTIR. Bu test o iddiayı koruyor — biri modeli "basitleştirip"
 | her alana kendi derslerini kopyalarsa burada patlar.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

it('sayısal ve eşit ağırlık aynı AYT Matematik kaydını paylaşır', function (): void {
    $ayt = Course::query()->where('code', 'ayt_matematik')->firstOrFail();

    $say = ExamVariant::query()->where('code', 'yks_say')->firstOrFail();
    $ea = ExamVariant::query()->where('code', 'yks_ea')->firstOrFail();

    expect($say->courses->pluck('id'))->toContain($ayt->id)
        ->and($ea->courses->pluck('id'))->toContain($ayt->id);

    // İçerik kopyalanmadığının kanıtı: kod tekil.
    expect(Course::query()->where('code', 'ayt_matematik')->count())->toBe(1);
});

it('TYT dersleri tüm alanlarda ortaktır, yalnızca sırası değişir', function (): void {
    $variants = ExamVariant::query()->whereIn('code', ['yks_say', 'yks_ea', 'yks_soz'])->get();

    $tytIdsPerVariant = $variants->map(
        fn (ExamVariant $v): array => $v->courses
            ->filter(fn (Course $c): bool => $c->scope->value === 'tyt')
            ->pluck('id')->sort()->values()->all()
    );

    expect($tytIdsPerVariant->unique()->count())->toBe(1)   // aynı ders kümesi
        ->and($tytIdsPerVariant->first())->toHaveCount(9);

    // Sıralama alana göre farklı: Sayısal'da Matematik ilk sırada.
    $say = $variants->firstWhere('code', 'yks_say');
    expect($say->courses->first()->code)->toBe('tyt_matematik');

    $soz = $variants->firstWhere('code', 'yks_soz');
    expect($soz->courses->first()->code)->toBe('tyt_turkce');
});

it('dersler oturuma göre gruplanır — TYT ve AYT sekmeleri', function (): void {
    $say = ExamVariant::query()->where('code', 'yks_say')->firstOrFail();

    $bySection = $say->courses->groupBy(fn (Course $c): int => $c->pivot->exam_section_id);

    expect($bySection)->toHaveCount(2);   // TYT + AYT, YDT yok

    $aytCourses = $say->courses
        ->filter(fn (Course $c): bool => $c->scope->value === 'ayt')
        ->pluck('code')->sort()->values()->all();

    expect($aytCourses)->toBe(['ayt_biyoloji', 'ayt_fizik', 'ayt_kimya', 'ayt_matematik']);
});

it('belirsiz alan yalnızca TYT görür', function (): void {
    $variant = ExamVariant::query()->where('code', 'yks_undecided')->firstOrFail();

    expect($variant->courses)->toHaveCount(9)
        ->and($variant->courses->every(fn (Course $c): bool => $c->scope->value === 'tyt'))->toBeTrue();
});

it('premium ders kilidi eşleme satırında tutulur, kodda değil', function (): void {
    $say = ExamVariant::query()->where('code', 'yks_say')->firstOrFail();

    $din = $say->courses->firstWhere('code', 'tyt_din');
    $matematik = $say->courses->firstWhere('code', 'tyt_matematik');

    expect($din->pivot->access)->toBe('premium')
        ->and($matematik->pivot->access)->toBe('free');
});

it('dil alanı YDT oturumunu görür, AYT görmez', function (): void {
    $dil = ExamVariant::query()->where('code', 'yks_dil')->firstOrFail();

    $scopes = $dil->courses->pluck('scope')->map->value->unique()->sort()->values()->all();

    expect($scopes)->toBe(['tyt', 'ydt']);
});

it('sınav ağırlıkları TYT oturumunun 120 sorusunu verir', function (): void {
    $say = ExamVariant::query()->where('code', 'yks_say')->firstOrFail();

    $total = $say->courses
        ->filter(fn (Course $c): bool => $c->scope->value === 'tyt')
        ->sum(fn (Course $c): int => (int) $c->pivot->exam_weight);

    expect($total)->toBe(120);
});
