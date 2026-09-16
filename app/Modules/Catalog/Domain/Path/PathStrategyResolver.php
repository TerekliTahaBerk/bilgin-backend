<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Path;

/**
 * Öğrenci bağlamına göre strateji seçer.
 *
 * Faz 2'de WeaknessFirstPath eklendiğinde değişecek tek yer burasıdır;
 * çağıran taraflar (GetCoursePath) hangi stratejinin seçildiğini bilmez.
 */
final readonly class PathStrategyResolver
{
    public function resolve(LearnerContext $learner): PathStrategy
    {
        return $learner->gradeLevel !== null
            ? new GradeAwarePath
            : new SequentialPath;
    }
}
