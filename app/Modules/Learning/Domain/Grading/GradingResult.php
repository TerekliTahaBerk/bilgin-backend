<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading;

/**
 * Her grader AYNI sonucu döndürür (LSP): çağıran taraf tipe göre dallanmaz.
 *
 * Kısmi puanlı tipler (eşleştirme, sıralama) partialScore kullanır; diğerleri
 * 0.0 veya 1.0 döner. correctAnswer yalnızca cevap verildikten SONRA
 * istemciye gösterilir.
 */
final readonly class GradingResult
{
    /** @param  array<string, mixed>  $correctAnswer */
    private function __construct(
        public bool $isCorrect,
        public float $partialScore,
        public array $correctAnswer,
    ) {}

    /** @param  array<string, mixed>  $correctAnswer */
    public static function correct(array $correctAnswer = []): self
    {
        return new self(true, 1.0, $correctAnswer);
    }

    /** @param  array<string, mixed>  $correctAnswer */
    public static function wrong(array $correctAnswer = []): self
    {
        return new self(false, 0.0, $correctAnswer);
    }

    /**
     * Kısmi puan. Eşiği geçmeyen kısmi cevap YANLIŞ sayılır — öğrenci
     * 4 eşleştirmenin 1'ini tutturup "doğru" geri bildirimi almamalı.
     *
     * @param  array<string, mixed>  $correctAnswer
     */
    public static function partial(float $score, array $correctAnswer = [], float $threshold = 1.0): self
    {
        $clamped = max(0.0, min(1.0, $score));

        return new self($clamped >= $threshold, $clamped, $correctAnswer);
    }

    public function isWrong(): bool
    {
        return ! $this->isCorrect;
    }
}
