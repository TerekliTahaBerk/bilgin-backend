<?php

declare(strict_types=1);

namespace App\Modules\Ads\Domain\Enum;

enum AdPlacement: string
{
    case HeartRefill = 'heart_refill';
    case StreakFreeze = 'streak_freeze';

    public static function fromCustomData(?string $customData): self
    {
        return self::tryFrom((string) $customData) ?? self::HeartRefill;
    }
}
