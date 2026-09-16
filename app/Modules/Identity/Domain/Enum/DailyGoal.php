<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enum;

/** Onboarding adım 7: "Günde kaç tur?" */
enum DailyGoal: int
{
    case Sakin = 1;
    case Duzenli = 3;
    case SinavModu = 6;

    public function label(): string
    {
        return match ($this) {
            self::Sakin => 'Sakin',
            self::Duzenli => 'Düzenli',
            self::SinavModu => 'Sınav modu',
        };
    }

    public function estimatedMinutes(): int
    {
        return $this->value * 5;
    }
}
