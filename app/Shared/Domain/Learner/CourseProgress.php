<?php

declare(strict_types=1);

namespace App\Shared\Domain\Learner;

/**
 * Bir dersteki ilerleme — ders listesinin künye satırı.
 *
 * Curriculum modülü ders listesini kuruyor ama ilerleme Learning'in
 * tablosunda duruyor. İki modül birbirini TANIMAZ; aralarındaki tek şey
 * bu DTO ve onu döndüren arayüz.
 *
 * Hiç çalışılmamış ders için bu nesne ÜRETİLMEZ, `null` geçilir. Sıfır
 * değerli bir nesne üretmek ("level 1 · 0/0") başlanmamış bir dersi
 * başlanmış gibi gösterirdi ve arayüzün ikisini ayırt etmesi imkânsızlaşırdı.
 */
final readonly class CourseProgress
{
    public function __construct(
        public int $level = 1,
        public int $xp = 0,
        public int $completedUnits = 0,
        public int $totalUnits = 0,
    ) {}

}
