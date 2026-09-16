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
    ) {}

    public function heartsDepleted(): bool
    {
        return $this->hearts?->isDepleted() ?? false;
    }
}
