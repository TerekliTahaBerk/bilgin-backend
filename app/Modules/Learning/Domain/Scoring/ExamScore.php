<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Scoring;

/** Deneme sonucu — tasarımdaki "net / puan tahmini" ekranının verisi. */
final readonly class ExamScore
{
    public function __construct(
        public int $correct,
        public int $wrong,
        public int $blank,
        public float $net,
        public float $estimatedScore,
    ) {}
}
