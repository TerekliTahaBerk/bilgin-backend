<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Path;

/**
 * Öğrencinin sınıfına göre sıralar.
 *
 * Mantık: kendi sınıfının ve altındaki sınıfların üniteleri önce gelir
 * (öğrenci bunları işledi, tekrar edebilir); ileri sınıfların üniteleri
 * sona atılır. 10. sınıf öğrencisine 12. sınıf konusunu ilk sırada
 * göstermek, ürünün ilk gün terk edilmesinin en kolay yoludur.
 *
 * Her iki grup kendi içinde müfredat sırasını korur.
 */
final readonly class GradeAwarePath implements PathStrategy
{
    public function name(): string
    {
        return 'grade_aware';
    }

    /**
     * @param  list<UnitCard>  $units
     * @return list<int>
     */
    public function order(array $units, LearnerContext $learner): array
    {
        $grade = $learner->gradeLevel;

        if ($grade === null) {
            return (new SequentialPath)->order($units, $learner);
        }

        $sorted = $units;

        usort($sorted, static function (UnitCard $a, UnitCard $b) use ($grade): int {
            $aAhead = $a->gradeLevel !== null && $a->gradeLevel > $grade;
            $bAhead = $b->gradeLevel !== null && $b->gradeLevel > $grade;

            return $aAhead === $bAhead
                ? $a->sortOrder <=> $b->sortOrder
                : ($aAhead ? 1 : -1);
        });

        return array_map(static fn (UnitCard $u): int => $u->id, $sorted);
    }
}
