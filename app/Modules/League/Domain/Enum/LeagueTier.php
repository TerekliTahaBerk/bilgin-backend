<?php

declare(strict_types=1);

namespace App\Modules\League\Domain\Enum;

/** Tasarımdaki "Zümrüt Lig" bu listenin dördüncüsü. */
enum LeagueTier: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';
    case Emerald = 'emerald';
    case Sapphire = 'sapphire';
    case Diamond = 'diamond';
    case Champion = 'champion';

    public function label(): string
    {
        return match ($this) {
            self::Bronze => 'Bronz Lig',
            self::Silver => 'Gümüş Lig',
            self::Gold => 'Altın Lig',
            self::Emerald => 'Zümrüt Lig',
            self::Sapphire => 'Safir Lig',
            self::Diamond => 'Elmas Lig',
            self::Champion => 'Şampiyon Lig',
        };
    }

    public function next(): self
    {
        $tiers = self::cases();
        $index = array_search($this, $tiers, true);

        // Şampiyon en üst: daha yukarısı yok, orada kalınır.
        return $tiers[min((int) $index + 1, count($tiers) - 1)];
    }

    public function previous(): self
    {
        $tiers = self::cases();
        $index = array_search($this, $tiers, true);

        // Bronz en alt: düşme yok. Yeni başlayanla düşen aynı yerde buluşur
        // ama bronzdan aşağı atmak kimseyi motive etmez.
        return $tiers[max((int) $index - 1, 0)];
    }

    public function isTop(): bool
    {
        return $this === self::Champion;
    }

    public function isBottom(): bool
    {
        return $this === self::Bronze;
    }
}
