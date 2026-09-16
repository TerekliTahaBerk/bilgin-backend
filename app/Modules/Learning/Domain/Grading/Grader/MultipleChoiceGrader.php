<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading\Grader;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Learning\Domain\Grading\ExerciseContent;
use App\Modules\Learning\Domain\Grading\ExerciseGrader;
use App\Modules\Learning\Domain\Grading\GradingResult;
use App\Modules\Learning\Domain\Grading\SubmittedAnswer;

/** Tüm derslerde fallback: çoktan seçmeli. */
final readonly class MultipleChoiceGrader implements ExerciseGrader
{
    public function type(): ExerciseType
    {
        return ExerciseType::MultipleChoice;
    }

    public function grade(ExerciseContent $exercise, SubmittedAnswer $answer): GradingResult
    {
        $correct = (string) $exercise->key('correct_option_id');
        $given = $answer->string('option_id');

        return $given === $correct
            ? GradingResult::correct(['option_id' => $correct])
            : GradingResult::wrong(['option_id' => $correct]);
    }
}
