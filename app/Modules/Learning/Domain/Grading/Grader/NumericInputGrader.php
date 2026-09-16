<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading\Grader;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Learning\Domain\Grading\ExerciseContent;
use App\Modules\Learning\Domain\Grading\ExerciseGrader;
use App\Modules\Learning\Domain\Grading\GradingResult;
use App\Modules\Learning\Domain\Grading\SubmittedAnswer;

/**
 * Sayısal giriş (Matematik, Fizik, Kimya).
 *
 * tolerance, ondalıklı sonuçlarda yuvarlama farkının öğrenciyi
 * cezalandırmaması için: "0.333" ile "0.33" aynı cevaptır.
 */
final readonly class NumericInputGrader implements ExerciseGrader
{
    public function type(): ExerciseType
    {
        return ExerciseType::NumericInput;
    }

    public function grade(ExerciseContent $exercise, SubmittedAnswer $answer): GradingResult
    {
        $expected = (float) $exercise->key('value');
        $tolerance = (float) ($exercise->key('tolerance') ?? 0);
        $given = $answer->get('value');

        if (! is_numeric($given)) {
            return GradingResult::wrong(['value' => $expected]);
        }

        return abs((float) $given - $expected) <= $tolerance
            ? GradingResult::correct(['value' => $expected])
            : GradingResult::wrong(['value' => $expected]);
    }
}
