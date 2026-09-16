<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Path;

/** Yolun kime göre kurulacağı. */
final readonly class LearnerContext
{
    public function __construct(
        public ?int $gradeLevel = null,        // 8..12; mezun → 12
        public ?int $targetExamYear = null,
        public bool $hasPremium = false,
        public ?int $currentYear = null,
    ) {}

    /** Sınava kaç yıl kaldı? Bilinmiyorsa null. */
    public function yearsToExam(): ?int
    {
        if ($this->targetExamYear === null || $this->currentYear === null) {
            return null;
        }

        return max(0, $this->targetExamYear - $this->currentYear);
    }
}
