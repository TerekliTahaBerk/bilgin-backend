<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading\Grader;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Learning\Domain\Grading\ExerciseContent;
use App\Modules\Learning\Domain\Grading\ExerciseGrader;
use App\Modules\Learning\Domain\Grading\GradingResult;
use App\Modules\Learning\Domain\Grading\SubmittedAnswer;

/**
 * Kronolojik / mantıksal sıralama (Tarih, Fen süreçleri, işlem adımları).
 *
 * Kısmi puan "kaç öğe doğru yerde" değil, Kendall benzeri bir ölçüyle
 * verilir: ardışık ikili sıralamalardan kaçı doğru. Tek bir öğeyi kaydıran
 * öğrenci sıfır almaz, çünkü kronolojiyi büyük ölçüde biliyordur.
 */
final readonly class OrderingGrader implements ExerciseGrader
{
    public function type(): ExerciseType
    {
        return ExerciseType::Ordering;
    }

    public function grade(ExerciseContent $exercise, SubmittedAnswer $answer): GradingResult
    {
        $expected = $exercise->key('order');
        $expected = is_array($expected) ? array_values(array_map(strval(...), $expected)) : [];
        $given = $answer->stringList('order');

        $correctAnswer = ['order' => $expected];

        if ($expected === [] || count($given) !== count($expected)) {
            return GradingResult::wrong($correctAnswer);
        }

        if ($given === $expected) {
            return GradingResult::correct($correctAnswer);
        }

        return GradingResult::partial($this->pairwiseScore($expected, $given), $correctAnswer);
    }

    /**
     * @param  list<string>  $expected
     * @param  list<string>  $given
     */
    private function pairwiseScore(array $expected, array $given): float
    {
        $rank = array_flip($expected);
        $total = 0;
        $correct = 0;

        for ($i = 0; $i < count($given); $i++) {
            for ($j = $i + 1; $j < count($given); $j++) {
                if (! isset($rank[$given[$i]], $rank[$given[$j]])) {
                    return 0.0;   // listede olmayan öğe gönderilmiş
                }

                $total++;
                $rank[$given[$i]] < $rank[$given[$j]] && $correct++;
            }
        }

        return $total === 0 ? 0.0 : $correct / $total;
    }
}
