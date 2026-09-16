<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Application\UseCase;

use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamVariant;

final readonly class LearnerCoursesView
{
    /** @param  list<SectionCoursesView>  $sections */
    public function __construct(
        public ExamVariant $variant,
        public array $sections,
    ) {}

    public function courseCount(): int
    {
        return array_sum(array_map(
            static fn (SectionCoursesView $s): int => count($s->courses),
            $this->sections,
        ));
    }
}
