<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading\Grader;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Learning\Domain\Grading\ExerciseContent;
use App\Modules\Learning\Domain\Grading\ExerciseGrader;
use App\Modules\Learning\Domain\Grading\GradingResult;
use App\Modules\Learning\Domain\Grading\SubmittedAnswer;

/** Hızlı tur / Mini Challenge icin ideal: doğru–yanlış. */
final readonly class TrueFalseGrader implements ExerciseGrader
{
    public function type(): ExerciseType
    {
        return ExerciseType::TrueFalse;
    }

    public function grade(ExerciseContent $exercise, SubmittedAnswer $answer): GradingResult
    {
        $correct = (bool) $exercise->key('value');
        $given = $answer->get('value');

        return is_bool($given) && $given === $correct
            ? GradingResult::correct(['value' => $correct])
            : GradingResult::wrong(['value' => $correct]);
    }
}
