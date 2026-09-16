<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Application\UseCase;

use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamSection;

/** Bir sekme (TYT / AYT / YDT) ve altındaki dersler. */
final readonly class SectionCoursesView
{
    /** @param  list<LearnerCourseView>  $courses */
    public function __construct(
        public ExamSection $section,
        public array $courses,
    ) {}

    public function with(LearnerCourseView $course): self
    {
        return new self($this->section, [...$this->courses, $course]);
    }
}
