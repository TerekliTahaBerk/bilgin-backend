<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Validation\Validator;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ContentValidationResult;
use App\Modules\Catalog\Domain\Validation\ExerciseContentValidator;
use App\Modules\Catalog\Domain\Validation\Rules;

final class FlashcardValidator implements ExerciseContentValidator
{
    use Rules;

    public function type(): ExerciseType
    {
        return ExerciseType::Flashcard;
    }

    public function validate(array $content, array $answerKey): ContentValidationResult
    {
        $errors = [
            ...$this->requireText($content['front'] ?? null, 'Ön yüz'),
            ...$this->requireText($content['back'] ?? null, 'Arka yüz'),
        ];

        return $errors === [] ? ContentValidationResult::valid() : ContentValidationResult::invalid($errors);
    }
}
