<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading\Grader;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Learning\Domain\Grading\ExerciseContent;
use App\Modules\Learning\Domain\Grading\ExerciseGrader;
use App\Modules\Learning\Domain\Grading\GradingResult;
use App\Modules\Learning\Domain\Grading\SubmittedAnswer;

/**
 * Eşleştirme (Tarih olay-tarih, Coğrafya ülke-başkent, Kimya element-sembol).
 *
 * Kısmi puan verilebilir ama "doğru" sayılmak için TAMAMI tutmalı:
 * 4 eşleştirmenin 1'ini tutturan öğrenci kutlama animasyonu görmemeli.
 * Kısmi skor yine de ilerleme yüzdesine ve konu analizine yansır.
 */
final readonly class MatchingGrader implements ExerciseGrader
{
    public function type(): ExerciseType
    {
        return ExerciseType::Matching;
    }

    public function grade(ExerciseContent $exercise, SubmittedAnswer $answer): GradingResult
    {
        $expected = $exercise->key('pairs');
        $expected = is_array($expected) ? $expected : [];
        $given = $answer->stringMap('pairs');

        $correctAnswer = ['pairs' => $expected];

        if ($expected === []) {
            return GradingResult::wrong($correctAnswer);
        }

        $matched = 0;

        foreach ($expected as $left => $right) {
            if (($given[(string) $left] ?? null) === (string) $right) {
                $matched++;
            }
        }

        $partialCredit = (bool) ($exercise->key('partial_credit') ?? false);
        $score = $matched / count($expected);

        return $partialCredit
            ? GradingResult::partial($score, $correctAnswer)
            : ($score >= 1.0 ? GradingResult::correct($correctAnswer) : GradingResult::wrong($correctAnswer));
    }
}
