<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Application\UseCase;

use App\Modules\Curriculum\Domain\ReadModel\VariantCourseRow;

final readonly class LearnerCourseView
{
    public function __construct(
        public VariantCourseRow $row,
        public bool $comingSoon,
        public bool $locked,
        public ?string $placeholderLabel,
    ) {}

    public function lockReason(): ?string
    {
        return $this->locked ? 'PREMIUM_REQUIRED' : null;
    }
}
