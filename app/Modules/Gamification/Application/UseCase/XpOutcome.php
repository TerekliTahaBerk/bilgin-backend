<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Application\UseCase;

use App\Modules\Gamification\Domain\Streak\StreakState;
use App\Modules\Gamification\Domain\Xp\XpAward;

final readonly class XpOutcome
{
    public function __construct(
        public XpAward $award,
        public int $levelBefore,
        public int $levelAfter,
        public int $totalXp,
        public int $nextLevelXp,
        public StreakState $streak,
    ) {}

    public function leveledUp(): bool
    {
        return $this->levelAfter > $this->levelBefore;
    }
}
