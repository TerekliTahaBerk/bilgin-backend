<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Selection;

final readonly class SelectionResult
{
    /** @param  list<ExerciseRef>  $exercises */
    public function __construct(
        public array $exercises,
        public int $requested,
        public bool $relaxed = false,
    ) {}

    public function isComplete(): bool
    {
        return count($this->exercises) >= $this->requested;
    }

    public function shortfall(): int
    {
        return max(0, $this->requested - count($this->exercises));
    }
}
