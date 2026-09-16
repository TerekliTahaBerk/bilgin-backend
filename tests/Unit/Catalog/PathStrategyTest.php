<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Path\GradeAwarePath;
use App\Modules\Catalog\Domain\Path\LearnerContext;
use App\Modules\Catalog\Domain\Path\PathStrategyResolver;
use App\Modules\Catalog\Domain\Path\SequentialPath;
use App\Modules\Catalog\Domain\Path\UnitCard;

/*
 | Aynı içerik, öğrenciye göre farklı sıra. İçeriği çoğaltmadan
 | kişiselleştirmenin yolu bu; dolayısıyla sıralama mantığı korunmalı.
 */

/** 9, 10, 11 ve 12. sınıf ünitelerinden oluşan bir ders. */
function units(): array
{
    return [
        new UnitCard(id: 1, sortOrder: 1, gradeLevel: 9),
        new UnitCard(id: 2, sortOrder: 2, gradeLevel: 10),
        new UnitCard(id: 3, sortOrder: 3, gradeLevel: 11),
        new UnitCard(id: 4, sortOrder: 4, gradeLevel: 12),
    ];
}

it('sequential müfredat sırasını korur', function (): void {
    $order = (new SequentialPath)->order(units(), new LearnerContext);

    expect($order)->toBe([1, 2, 3, 4]);
});

it('sequential karışık gelen üniteleri sıraya sokar', function (): void {
    $shuffled = [
        new UnitCard(3, 3),
        new UnitCard(1, 1),
        new UnitCard(2, 2),
    ];

    expect((new SequentialPath)->order($shuffled, new LearnerContext))->toBe([1, 2, 3]);
});

it('10. sınıf öğrencisine ileri sınıf ünitelerini sona atar', function (): void {
    // 12. sınıf konusunu ilk sırada göstermek, ürünün ilk gün
    // terk edilmesinin en kolay yoludur.
    $order = (new GradeAwarePath)->order(units(), new LearnerContext(gradeLevel: 10));

    expect($order)->toBe([1, 2, 3, 4])
        ->and(array_slice($order, 0, 2))->toBe([1, 2]);   // 9 ve 10 önce
});

it('ileri üniteler sona atılırken müfredat sırası bozulmaz', function (): void {
    $mixed = [
        new UnitCard(id: 10, sortOrder: 1, gradeLevel: 12),  // ileri
        new UnitCard(id: 20, sortOrder: 2, gradeLevel: 9),
        new UnitCard(id: 30, sortOrder: 3, gradeLevel: 12),  // ileri
        new UnitCard(id: 40, sortOrder: 4, gradeLevel: 10),
    ];

    $order = (new GradeAwarePath)->order($mixed, new LearnerContext(gradeLevel: 10));

    // Önce öğrencinin seviyesindekiler (kendi içinde sırayla), sonra ilerisi.
    expect($order)->toBe([20, 40, 10, 30]);
});

it('mezun öğrenci tüm üniteleri müfredat sırasında görür', function (): void {
    $order = (new GradeAwarePath)->order(units(), new LearnerContext(gradeLevel: 12));

    expect($order)->toBe([1, 2, 3, 4]);
});

it('sınıfı bilinmeyen öğrenci için sequential\'a düşer', function (): void {
    $order = (new GradeAwarePath)->order(units(), new LearnerContext);

    expect($order)->toBe([1, 2, 3, 4]);
});

it('sınıf seviyesi olmayan üniteler ileri sayılmaz', function (): void {
    $unlabeled = [
        new UnitCard(id: 1, sortOrder: 1, gradeLevel: null),
        new UnitCard(id: 2, sortOrder: 2, gradeLevel: 12),
    ];

    // Etiketsiz ünite gizlenmemeli; belirsizlik öğrenciyi cezalandırmamalı.
    expect((new GradeAwarePath)->order($unlabeled, new LearnerContext(gradeLevel: 9)))
        ->toBe([1, 2]);
});

it('resolver sınıf bilgisine göre strateji seçer', function (): void {
    $resolver = new PathStrategyResolver;

    expect($resolver->resolve(new LearnerContext(gradeLevel: 11))->name())->toBe('grade_aware')
        ->and($resolver->resolve(new LearnerContext)->name())->toBe('sequential');
});

it('sınava kalan yılı hesaplar', function (): void {
    $learner = new LearnerContext(targetExamYear: 2027, currentYear: 2026);

    expect($learner->yearsToExam())->toBe(1)
        ->and((new LearnerContext)->yearsToExam())->toBeNull();
});
