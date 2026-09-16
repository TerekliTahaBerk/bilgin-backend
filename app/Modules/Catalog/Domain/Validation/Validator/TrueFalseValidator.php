<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Validation\Validator;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ContentValidationResult;
use App\Modules\Catalog\Domain\Validation\ExerciseContentValidator;
use App\Modules\Catalog\Domain\Validation\Rules;

final class TrueFalseValidator implements ExerciseContentValidator
{
    use Rules;

    public function type(): ExerciseType
    {
        return ExerciseType::TrueFalse;
    }

    public function validate(array $content, array $answerKey): ContentValidationResult
    {
        $errors = $this->requireText($content['statement'] ?? null, 'İfade');

        // Grader katı karşılaştırma yapıyor: "true" string'i doğru sayılmaz.
        if (! isset($answerKey['value']) || ! is_bool($answerKey['value'])) {
            $errors[] = 'Cevap true veya false olmalı (metin değil).';
        }

        return $errors === [] ? ContentValidationResult::valid() : ContentValidationResult::invalid($errors);
    }
}
