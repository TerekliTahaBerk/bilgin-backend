<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading\Grader;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Learning\Domain\Grading\ExerciseContent;
use App\Modules\Learning\Domain\Grading\ExerciseGrader;
use App\Modules\Learning\Domain\Grading\GradingResult;
use App\Modules\Learning\Domain\Grading\SubmittedAnswer;
use App\Modules\Learning\Domain\Grading\TextNormalizer;

/**
 * Boşluk doldurma (Türkçe, Tarih, Din Kültürü, Yabancı Dil).
 *
 * Birden fazla boşluk olabilir; hepsi doğru olmalı. Metin karşılaştırması
 * Türkçe'ye duyarlı normalize edilir — "Töre" ile "töre" aynı cevaptır,
 * ama "TORE" değildir (ı/i ve ö/o ayrımı korunur).
 */
final readonly class FillBlankGrader implements ExerciseGrader
{
    public function __construct(private TextNormalizer $normalizer = new TextNormalizer) {}

    public function type(): ExerciseType
    {
        return ExerciseType::FillBlank;
    }

    public function grade(ExerciseContent $exercise, SubmittedAnswer $answer): GradingResult
    {
        $expected = $exercise->key('blanks');
        $expected = is_array($expected) ? array_values($expected) : [];
        $given = $answer->stringList('blanks');

        $correctAnswer = ['blanks' => $expected];

        if (count($given) !== count($expected) || $expected === []) {
            return GradingResult::wrong($correctAnswer);
        }

        foreach ($expected as $index => $word) {
            if (! $this->normalizer->equals((string) $word, $given[$index])) {
                return GradingResult::wrong($correctAnswer);
            }
        }

        return GradingResult::correct($correctAnswer);
    }
}
