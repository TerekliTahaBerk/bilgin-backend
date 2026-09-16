<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Domain\Streak;

final readonly class StreakState
{
    public function __construct(
        public int $current,
        public int $longest,
        public ?string $lastStudyDate,
        public bool $extendedToday = false,
        public ?int $milestone = null,
    ) {}
}
