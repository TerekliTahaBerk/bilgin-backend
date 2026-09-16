<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Validation\Validator;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ContentValidationResult;
use App\Modules\Catalog\Domain\Validation\ExerciseContentValidator;
use App\Modules\Catalog\Domain\Validation\Rules;

final class MultipleChoiceValidator implements ExerciseContentValidator
{
    use Rules;

    public function type(): ExerciseType
    {
        return ExerciseType::MultipleChoice;
    }

    public function validate(array $content, array $answerKey): ContentValidationResult
    {
        $errors = $this->requireText($content['stem'] ?? null, 'Soru kökü');

        [$optionIds, $optionErrors] = $this->idList($content['options'] ?? null, 'Şıklar', 2);
        $errors = [...$errors, ...$optionErrors];

        $correct = $answerKey['correct_option_id'] ?? null;

        if ($correct === null) {
            $errors[] = "Doğru şık ('correct_option_id') belirtilmeli.";
        } elseif ($optionIds !== [] && ! in_array((string) $correct, $optionIds, true)) {
            // Şıklar arasında olmayan bir doğru cevap, o soruyu ÇÖZÜLEMEZ yapar:
            // öğrenci ne seçerse seçsin yanlış sayılır ve canını kaybeder.
            $errors[] = "Doğru şık '{$correct}' şıklar arasında yok.";
        }

        return $errors === [] ? ContentValidationResult::valid() : ContentValidationResult::invalid($errors);
    }
}
