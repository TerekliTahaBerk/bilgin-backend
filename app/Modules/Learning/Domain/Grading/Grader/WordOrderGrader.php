<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading\Grader;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Learning\Domain\Grading\ExerciseContent;
use App\Modules\Learning\Domain\Grading\ExerciseGrader;
use App\Modules\Learning\Domain\Grading\GradingResult;
use App\Modules\Learning\Domain\Grading\SubmittedAnswer;

/**
 * Kelimeleri sıraya dizerek cümle kurma (Türkçe, YDS, YÖKDİL).
 *
 * Sıralamadan farkı: cümlede kısmi doğruluk anlamsızdır — cümle ya kuruludur
 * ya değildir. Bu yüzden kısmi puan yok.
 */
final readonly class WordOrderGrader implements ExerciseGrader
{
    public function type(): ExerciseType
    {
        return ExerciseType::WordOrder;
    }

    public function grade(ExerciseContent $exercise, SubmittedAnswer $answer): GradingResult
    {
        $expected = $exercise->key('order');
        $expected = is_array($expected) ? array_values(array_map(strval(...), $expected)) : [];
        $given = $answer->stringList('order');

        $correctAnswer = ['order' => $expected];

        return $expected !== [] && $given === $expected
            ? GradingResult::correct($correctAnswer)
            : GradingResult::wrong($correctAnswer);
    }
}
