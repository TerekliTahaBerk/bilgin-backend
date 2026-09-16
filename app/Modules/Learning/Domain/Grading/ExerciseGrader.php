<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading;

use App\Modules\Catalog\Domain\Enum\ExerciseType;

/**
 * Bir egzersiz tipinin cevabını değerlendirir.
 *
 * Yeni tip eklemek = yeni grader + registry'de bir satır. Mevcut hiçbir
 * sınıf açılmaz (OCP). Kod tabanında ExerciseType üzerinde switch YOKTUR;
 * çözümleme daima GraderRegistry üzerindendir.
 */
interface ExerciseGrader
{
    public function type(): ExerciseType;

    public function grade(ExerciseContent $exercise, SubmittedAnswer $answer): GradingResult;
}
