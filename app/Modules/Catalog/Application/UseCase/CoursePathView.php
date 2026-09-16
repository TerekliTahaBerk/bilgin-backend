<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCase;

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;

/** @param list<UnitPathView> $units */
final readonly class CoursePathView
{
    /** @param  list<UnitPathView>  $units */
    public function __construct(
        public Course $course,
        public string $pathStrategy,
        public array $units,
    ) {}

    public function completedUnits(): int
    {
        return count(array_filter($this->units, static fn (UnitPathView $u): bool => $u->isCompleted()));
    }
}
