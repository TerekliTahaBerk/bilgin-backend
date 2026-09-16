<?php

declare(strict_types=1);

namespace App\Modules\League\Domain\Promotion;

final readonly class StandingEntry
{
    public function __construct(
        public int $userId,
        public int $weeklyXp,
    ) {}
}
