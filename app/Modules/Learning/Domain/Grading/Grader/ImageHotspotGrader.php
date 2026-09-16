<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading\Grader;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Learning\Domain\Grading\ExerciseContent;
use App\Modules\Learning\Domain\Grading\ExerciseGrader;
use App\Modules\Learning\Domain\Grading\GradingResult;
use App\Modules\Learning\Domain\Grading\SubmittedAnswer;

/**
 * Harita/görsel üzerinde bölge işaretleme (Coğrafya).
 *
 * İstemci bölge kimliği gönderir, koordinat değil: dokunma hassasiyeti ve
 * ekran boyutu farkları sunucunun sorunu olmamalı.
 */
final readonly class ImageHotspotGrader implements ExerciseGrader
{
    public function type(): ExerciseType
    {
        return ExerciseType::ImageHotspot;
    }

    public function grade(ExerciseContent $exercise, SubmittedAnswer $answer): GradingResult
    {
        $expected = $exercise->key('hotspot_id');
        $correctAnswer = ['hotspot_id' => $expected];

        return $expected !== null && $answer->string('hotspot_id') === (string) $expected
            ? GradingResult::correct($correctAnswer)
            : GradingResult::wrong($correctAnswer);
    }
}
