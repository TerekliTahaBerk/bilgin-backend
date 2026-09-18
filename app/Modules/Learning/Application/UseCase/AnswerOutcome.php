<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\UseCase;

use App\Shared\Domain\Hearts\HeartBalance;

/** Cevap ekranının tek yanıtta ihtiyaç duyduğu her şey. */
final readonly class AnswerOutcome
{
    /** @param  array<string, mixed>  $correctAnswer */
    public function __construct(
        public bool $isCorrect,
        public float $partialScore,
        public array $correctAnswer,
        public ?string $explanation,
        public ?HeartBalance $hearts,
        public int $answered,
        public int $total,
        public bool $suspicious = false,
        public bool $idempotentReplay = false,
        /**
         * Doğru cevap istemciye gösterilsin mi.
         *
         * Denemede FALSE. Sınav provasında her sorudan sonra sonucu görmek
         * denemenin amacını bozar; ayrıca cevap anahtarını yanıtta göndermek,
         * arayüz göstermese bile araya giren birinin okumasına açık bırakır.
         */
        public bool $revealsAnswer = true,
    ) {}

    public function heartsDepleted(): bool
    {
        return $this->hearts?->isDepleted() ?? false;
    }
}
