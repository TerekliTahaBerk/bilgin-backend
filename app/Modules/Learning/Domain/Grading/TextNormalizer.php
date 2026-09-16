<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading;

/**
 * Türkçe metin karşılaştırması.
 *
 * mb_strtolower varsayılan ayarıyla "I" → "i" yapar; Türkçe'de doğrusu "ı"dır.
 * Bu fark "İSTANBUL" ile "istanbul"un eşleşmemesine yol açar ve öğrenciyi
 * haksız yere yanlış yapar — bu yüzden dönüşüm elle yapılıyor.
 *
 * Aksan/şapka KALDIRILMAZ: "kar" ile "kâr" farklı kelimelerdir.
 */
final readonly class TextNormalizer
{
    private const UPPER_TO_LOWER = [
        'I' => 'ı', 'İ' => 'i', 'Ş' => 'ş', 'Ğ' => 'ğ',
        'Ü' => 'ü', 'Ö' => 'ö', 'Ç' => 'ç',
    ];

    public function normalize(string $text): string
    {
        $text = strtr(trim($text), self::UPPER_TO_LOWER);
        $text = mb_strtolower($text, 'UTF-8');

        // Araya sıkışan fazla boşlukları tek boşluğa indir.
        return (string) preg_replace('/\s+/u', ' ', $text);
    }

    public function equals(string $expected, string $given): bool
    {
        return $this->normalize($expected) === $this->normalize($given);
    }
}
