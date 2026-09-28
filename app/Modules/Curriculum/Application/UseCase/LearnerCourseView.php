<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Application\UseCase;

use App\Modules\Curriculum\Domain\ReadModel\VariantCourseRow;
use App\Shared\Domain\Learner\CourseProgress;

final readonly class LearnerCourseView
{
    public function __construct(
        public VariantCourseRow $row,
        public bool $comingSoon,
        public bool $locked,
        public ?string $placeholderLabel,
        public ?CourseProgress $progress = null,
    ) {}

    public function lockReason(): ?string
    {
        return $this->locked ? 'PREMIUM_REQUIRED' : null;
    }
}
