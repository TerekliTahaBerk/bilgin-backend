<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Validation\Validator;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ContentValidationResult;
use App\Modules\Catalog\Domain\Validation\ExerciseContentValidator;
use App\Modules\Catalog\Domain\Validation\Rules;

final class MatchingValidator implements ExerciseContentValidator
{
    use Rules;

    public function type(): ExerciseType
    {
        return ExerciseType::Matching;
    }

    public function validate(array $content, array $answerKey): ContentValidationResult
    {
        [$leftIds, $leftErrors] = $this->idList($content['left'] ?? null, 'Sol sütun', 2);
        [$rightIds, $rightErrors] = $this->idList($content['right'] ?? null, 'Sağ sütun', 2);

        $errors = [...$leftErrors, ...$rightErrors];
        $pairs = $answerKey['pairs'] ?? null;

        if (! is_array($pairs) || $pairs === []) {
            return ContentValidationResult::invalid([...$errors, 'Eşleşme listesi boş olamaz.']);
        }

        // Eşleşmesi olmayan sol öğe, kısmi puanı kalıcı olarak eksiltir:
        // öğrenci hepsini doğru yapsa bile tam puan alamaz.
        foreach ($leftIds as $id) {
            if (! array_key_exists($id, $pairs)) {
                $errors[] = "Sol sütundaki '{$id}' için eşleşme tanımlanmamış.";
            }
        }

        foreach ($pairs as $left => $right) {
            if ($leftIds !== [] && ! in_array((string) $left, $leftIds, true)) {
                $errors[] = "Eşleşmede tanımsız sol öğe: '{$left}'.";
            }

            if ($rightIds !== [] && ! in_array((string) $right, $rightIds, true)) {
                $errors[] = "Eşleşmede tanımsız sağ öğe: '{$right}'.";
            }
        }

        return $errors === [] ? ContentValidationResult::valid() : ContentValidationResult::invalid($errors);
    }
}
