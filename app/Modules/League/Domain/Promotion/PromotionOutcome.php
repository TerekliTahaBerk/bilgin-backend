<?php

declare(strict_types=1);

namespace App\Modules\League\Domain\Promotion;

use App\Modules\League\Domain\Enum\LeagueTier;

final readonly class PromotionOutcome
{
    public function __construct(
        public int $userId,
        public int $rank,
        public string $result,
        public LeagueTier $nextTier,
    ) {}
}
