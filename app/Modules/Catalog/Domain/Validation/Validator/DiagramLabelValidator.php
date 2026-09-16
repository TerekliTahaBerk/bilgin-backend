<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Validation\Validator;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ContentValidationResult;
use App\Modules\Catalog\Domain\Validation\ExerciseContentValidator;
use App\Modules\Catalog\Domain\Validation\Rules;

final class DiagramLabelValidator implements ExerciseContentValidator
{
    use Rules;

    public function type(): ExerciseType
    {
        return ExerciseType::DiagramLabel;
    }

    public function validate(array $content, array $answerKey): ContentValidationResult
    {
        $errors = $this->requireText($content['instruction'] ?? null, 'Yönerge');

        if (! isset($content['image']) || ! is_string($content['image']) || $content['image'] === '') {
            $errors[] = 'Görsel anahtarı ("image") gerekli.';
        }

        [$slotIds, $slotErrors] = $this->idList($content['slots'] ?? null, 'Etiket yerleri', 2);
        $errors = [...$errors, ...$slotErrors];

        $labels = $answerKey['labels'] ?? null;

        if (! is_array($labels) || $labels === []) {
            return ContentValidationResult::invalid([...$errors, 'Etiket listesi boş olamaz.']);
        }

        // Etiketsiz kalan her yer, kısmi puanın tavanını kalıcı olarak düşürür.
        foreach ($slotIds as $id) {
            if (! array_key_exists($id, $labels)) {
                $errors[] = "'{$id}' için doğru etiket tanımlanmamış.";
            }
        }

        return $errors === [] ? ContentValidationResult::valid() : ContentValidationResult::invalid($errors);
    }
}
