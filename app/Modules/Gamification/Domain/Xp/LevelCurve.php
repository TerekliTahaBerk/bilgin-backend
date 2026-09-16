<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Domain\Xp;

/**
 * Toplam XP'den level hesaplar.
 *
 * Tablo tabanlı, formül değil: erken seviyeler bilinçli olarak hızlı geçilir
 * (ilk oturumda level atlamak güçlü bir kanca), sonra fark açılır. Bir formül
 * bu eğriyi ürün kararı olmaktan çıkarıp matematiğe teslim ederdi.
 */
final readonly class LevelCurve
{
    /** @param  list<int>  $thresholds  kümülatif XP eşikleri */
    public function __construct(
        private array $thresholds,
        private int $stepAfterTable,
    ) {}

    public function levelFor(int $totalXp): int
    {
        $level = 1;

        foreach ($this->thresholds as $index => $threshold) {
            if ($totalXp >= $threshold) {
                $level = $index + 1;
            }
        }

        if ($level < count($this->thresholds)) {
            return $level;
        }

        $lastThreshold = $this->thresholds[count($this->thresholds) - 1];

        return count($this->thresholds) + intdiv($totalXp - $lastThreshold, $this->stepAfterTable);
    }

    /** Bu levelin başlangıç XP'si. */
    public function xpForLevel(int $level): int
    {
        if ($level <= count($this->thresholds)) {
            return $this->thresholds[max(0, $level - 1)];
        }

        $lastThreshold = $this->thresholds[count($this->thresholds) - 1];

        return $lastThreshold + ($level - count($this->thresholds)) * $this->stepAfterTable;
    }

    /** Bir sonraki levele geçiş XP'si — ilerleme çubuğunun üst sınırı. */
    public function nextLevelXp(int $totalXp): int
    {
        return $this->xpForLevel($this->levelFor($totalXp) + 1);
    }
}
