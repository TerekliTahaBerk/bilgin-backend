<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading\Grader;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Learning\Domain\Grading\ExerciseContent;
use App\Modules\Learning\Domain\Grading\ExerciseGrader;
use App\Modules\Learning\Domain\Grading\GradingResult;
use App\Modules\Learning\Domain\Grading\SubmittedAnswer;

/**
 * Şema/diyagram etiketleme (Biyoloji hücre, Fizik devre).
 *
 * Yapı olarak eşleştirmeye benzer (etiket → konum) ama kısmi puan burada
 * her zaman açıktır: 8 etiketli bir hücre şemasında 6'yı bilmek gerçek
 * bir bilgidir ve konu analizine öyle yansımalıdır.
 */
final readonly class DiagramLabelGrader implements ExerciseGrader
{
    public function type(): ExerciseType
    {
        return ExerciseType::DiagramLabel;
    }

    public function grade(ExerciseContent $exercise, SubmittedAnswer $answer): GradingResult
    {
        $expected = $exercise->key('labels');
        $expected = is_array($expected) ? $expected : [];
        $given = $answer->stringMap('labels');

        $correctAnswer = ['labels' => $expected];

        if ($expected === []) {
            return GradingResult::wrong($correctAnswer);
        }

        $matched = 0;

        foreach ($expected as $slot => $label) {
            if (($given[(string) $slot] ?? null) === (string) $label) {
                $matched++;
            }
        }

        return GradingResult::partial($matched / count($expected), $correctAnswer);
    }
}
