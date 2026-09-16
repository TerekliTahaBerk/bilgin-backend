<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Validation\Validator;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ContentValidationResult;
use App\Modules\Catalog\Domain\Validation\ExerciseContentValidator;
use App\Modules\Catalog\Domain\Validation\Rules;

final class NumericInputValidator implements ExerciseContentValidator
{
    use Rules;

    public function type(): ExerciseType
    {
        return ExerciseType::NumericInput;
    }

    public function validate(array $content, array $answerKey): ContentValidationResult
    {
        $errors = $this->requireText($content['stem'] ?? null, 'Soru kökü');

        if (! isset($answerKey['value']) || ! is_numeric($answerKey['value'])) {
            $errors[] = 'Cevap sayısal olmalı.';
        }

        $tolerance = $answerKey['tolerance'] ?? 0;

        if (! is_numeric($tolerance) || (float) $tolerance < 0) {
            $errors[] = 'Tolerans negatif olamaz.';
        }

        return $errors === [] ? ContentValidationResult::valid() : ContentValidationResult::invalid($errors);
    }
}
