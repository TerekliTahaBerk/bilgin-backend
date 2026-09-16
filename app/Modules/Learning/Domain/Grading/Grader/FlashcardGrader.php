<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading\Grader;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Learning\Domain\Grading\ExerciseContent;
use App\Modules\Learning\Domain\Grading\ExerciseGrader;
use App\Modules\Learning\Domain\Grading\GradingResult;
use App\Modules\Learning\Domain\Grading\SubmittedAnswer;

/**
 * Flashcard (Hızlı Tekrar). Öğrenci kendi değerlendirir: "Biliyorum" / "Tekrar Et".
 *
 * Bu tip can HARCAMAZ mantığıyla kullanılır — kendini yanlış değerlendirmek
 * ceza sebebi olmamalı. Sonuç yine de tekrar kuyruğunu besler.
 */
final readonly class FlashcardGrader implements ExerciseGrader
{
    public function type(): ExerciseType
    {
        return ExerciseType::Flashcard;
    }

    public function grade(ExerciseContent $exercise, SubmittedAnswer $answer): GradingResult
    {
        return $answer->get('known') === true
            ? GradingResult::correct()
            : GradingResult::wrong();
    }
}
