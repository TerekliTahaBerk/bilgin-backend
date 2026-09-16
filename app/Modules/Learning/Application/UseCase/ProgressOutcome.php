<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\UseCase;

final readonly class ProgressOutcome
{
    /** @param  list<int>  $unlockedNodeIds */
    public function __construct(
        public bool $isFirstCompletion,
        public bool $nodeCompleted,
        public int $unitCompletionPercent,
        public bool $unitCompleted,
        public array $unlockedNodeIds,
    ) {}
}
