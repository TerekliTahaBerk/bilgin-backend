<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enum;

/**
 * Her tipin bir ExerciseGrader'ı ve bir ExerciseContentValidator'ı vardır.
 * Yeni tip eklemek = yeni enum case + 2 sınıf + 1 JSON şema. Hiçbir mevcut
 * sınıf açılmaz (OCP). Kod tabanında bu enum üzerinde switch YAPILMAZ;
 * çözümleme her zaman registry üzerindendir.
 */
enum ExerciseType: string
{
    case MultipleChoice = 'multiple_choice';
    case FillBlank = 'fill_blank';
    case Matching = 'matching';
    case Ordering = 'ordering';
    case Flashcard = 'flashcard';
    case TrueFalse = 'true_false';
    case WordOrder = 'word_order';
    case NumericInput = 'numeric_input';
    case ImageHotspot = 'image_hotspot';
    case DiagramLabel = 'diagram_label';

    /**
     * Bu tipin altında cevap verilmesi insan-dışı sayılan süre (ms).
     * Hile önleme: altında kalan cevap XP üretmez, is_suspicious işaretlenir.
     */
    public function minimumAnswerMs(): int
    {
        return match ($this) {
            self::TrueFalse, self::Flashcard => 800,
            self::MultipleChoice, self::NumericInput => 1200,
            default => 1500,
        };
    }
}
