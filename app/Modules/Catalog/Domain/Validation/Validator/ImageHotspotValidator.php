<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Validation\Validator;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ContentValidationResult;
use App\Modules\Catalog\Domain\Validation\ExerciseContentValidator;
use App\Modules\Catalog\Domain\Validation\Rules;

final class ImageHotspotValidator implements ExerciseContentValidator
{
    use Rules;

    public function type(): ExerciseType
    {
        return ExerciseType::ImageHotspot;
    }

    public function validate(array $content, array $answerKey): ContentValidationResult
    {
        $errors = $this->requireText($content['instruction'] ?? null, 'Yönerge');

        [$hotspotIds, $hotspotErrors] = $this->idList($content['hotspots'] ?? null, 'Bölgeler', 2);
        $errors = [...$errors, ...$hotspotErrors];

        // Görsel olmadan bu tip render edilemez; eksikliği ancak öğrenci
        // soruya geldiğinde boş ekran olarak ortaya çıkar.
        if (! isset($content['image']) || ! is_string($content['image']) || $content['image'] === '') {
            $errors[] = 'Görsel anahtarı ("image") gerekli.';
        }

        $correct = $answerKey['hotspot_id'] ?? null;

        if ($correct === null) {
            $errors[] = "Doğru bölge ('hotspot_id') belirtilmeli.";
        } elseif ($hotspotIds !== [] && ! in_array((string) $correct, $hotspotIds, true)) {
            $errors[] = "Doğru bölge '{$correct}' tanımlı bölgeler arasında yok.";
        }

        return $errors === [] ? ContentValidationResult::valid() : ContentValidationResult::invalid($errors);
    }
}
