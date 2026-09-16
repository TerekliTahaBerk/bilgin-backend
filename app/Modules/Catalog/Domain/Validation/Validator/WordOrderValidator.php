<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Validation\Validator;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ContentValidationResult;
use App\Modules\Catalog\Domain\Validation\ExerciseContentValidator;
use App\Modules\Catalog\Domain\Validation\Rules;

final class WordOrderValidator implements ExerciseContentValidator
{
    use Rules;

    public function type(): ExerciseType
    {
        return ExerciseType::WordOrder;
    }

    public function validate(array $content, array $answerKey): ContentValidationResult
    {
        $errors = $this->requireText($content['instruction'] ?? null, 'Yönerge');

        [$wordIds, $wordErrors] = $this->idList($content['words'] ?? null, 'Kelimeler', 2);
        $errors = [...$errors, ...$wordErrors];

        if ($wordIds !== []) {
            $errors = [...$errors, ...$this->requireExactOrder($answerKey['order'] ?? null, $wordIds, 'Doğru sıra')];
        }

        return $errors === [] ? ContentValidationResult::valid() : ContentValidationResult::invalid($errors);
    }
}
