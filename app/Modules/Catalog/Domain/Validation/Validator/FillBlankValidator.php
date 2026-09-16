<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Validation\Validator;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ContentValidationResult;
use App\Modules\Catalog\Domain\Validation\ExerciseContentValidator;
use App\Modules\Catalog\Domain\Validation\Rules;

final class FillBlankValidator implements ExerciseContentValidator
{
    use Rules;

    public function type(): ExerciseType
    {
        return ExerciseType::FillBlank;
    }

    public function validate(array $content, array $answerKey): ContentValidationResult
    {
        $template = $content['template'] ?? null;
        $errors = $this->requireText($template, 'Cümle şablonu');

        $placeholders = 0;

        if (is_string($template)) {
            preg_match_all('/\{\{\d+\}\}/', $template, $matches);
            $placeholders = count($matches[0]);

            if ($placeholders === 0) {
                $errors[] = 'Şablon en az bir boşluk içermeli: {{0}}';
            }
        }

        $blanks = $answerKey['blanks'] ?? null;

        if (! is_array($blanks) || $blanks === []) {
            $errors[] = 'Cevap listesi boş olamaz.';
        } elseif ($placeholders > 0 && count($blanks) !== $placeholders) {
            // Sayı tutmazsa grader her zaman "yanlış" döner.
            $errors[] = "Şablonda {$placeholders} boşluk var ama ".count($blanks).' cevap verilmiş.';
        }

        $choices = $content['choices'] ?? [];

        if (is_array($choices) && $choices !== [] && is_array($blanks)) {
            foreach ($blanks as $blank) {
                if (! in_array($blank, $choices, true)) {
                    // Seçenekler arasında olmayan doğru cevap, soruyu çözülemez yapar.
                    $errors[] = "Doğru cevap '{$blank}' seçenekler arasında yok.";
                }
            }
        }

        return $errors === [] ? ContentValidationResult::valid() : ContentValidationResult::invalid($errors);
    }
}
