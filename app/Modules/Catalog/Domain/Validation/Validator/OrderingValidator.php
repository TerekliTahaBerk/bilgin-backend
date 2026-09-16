<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Validation\Validator;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ContentValidationResult;
use App\Modules\Catalog\Domain\Validation\ExerciseContentValidator;
use App\Modules\Catalog\Domain\Validation\Rules;

final class OrderingValidator implements ExerciseContentValidator
{
    use Rules;

    public function type(): ExerciseType
    {
        return ExerciseType::Ordering;
    }

    public function validate(array $content, array $answerKey): ContentValidationResult
    {
        $errors = $this->requireText($content['instruction'] ?? null, 'Yönerge');

        [$itemIds, $itemErrors] = $this->idList($content['items'] ?? null, 'Sıralanacak öğeler', 2);
        $errors = [...$errors, ...$itemErrors];

        if ($itemIds !== []) {
            $errors = [...$errors, ...$this->requireExactOrder($answerKey['order'] ?? null, $itemIds, 'Doğru sıra')];
        }

        return $errors === [] ? ContentValidationResult::valid() : ContentValidationResult::invalid($errors);
    }
}
