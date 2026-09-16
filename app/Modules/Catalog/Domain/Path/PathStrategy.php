<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Path;

/**
 * Aynı ders içeriğini öğrenciye göre farklı sırada sunar.
 *
 * İçerik tek, görünüm kişiye özeldir: 10. sınıf öğrencisi ile mezun aynı
 * dersin ünitelerini farklı sırada görür. Bu, içeriği çoğaltmadan
 * kişiselleştirmenin yoludur.
 *
 * @return list<int> sıralanmış ünite id'leri
 */
interface PathStrategy
{
    public function name(): string;

    /**
     * @param  list<UnitCard>  $units
     * @return list<int> sıralanmış ünite id'leri
     */
    public function order(array $units, LearnerContext $learner): array;
}
