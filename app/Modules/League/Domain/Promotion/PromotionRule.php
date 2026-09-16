<?php

declare(strict_types=1);

namespace App\Modules\League\Domain\Promotion;

use App\Modules\League\Domain\Enum\LeagueTier;

/**
 * Hafta sonu terfi/düşme kararı — saf hesap, veritabanı yok.
 *
 * Sıfır XP kazanmış üye DÜŞMEZ, olduğu yerde kalır: haftayı hiç oynamadan
 * geçiren biri zaten cezalandırılmış sayılır ve üstüne bir de düşürmek
 * geri dönmeyi zorlaştırır.
 */
final readonly class PromotionRule
{
    public function __construct(
        private int $promotionCount,
        private int $demotionCount,
    ) {}

    /**
     * @param  list<StandingEntry>  $standings  XP'ye göre sıralı (yüksekten düşüğe)
     * @return list<PromotionOutcome>
     */
    public function decide(array $standings, LeagueTier $tier): array
    {
        $total = count($standings);
        $outcomes = [];

        foreach ($standings as $index => $entry) {
            $rank = $index + 1;

            $outcomes[] = new PromotionOutcome(
                userId: $entry->userId,
                rank: $rank,
                result: $this->resultFor($rank, $total, $entry->weeklyXp, $tier),
                nextTier: $this->nextTierFor($rank, $total, $entry->weeklyXp, $tier),
            );
        }

        return $outcomes;
    }

    private function resultFor(int $rank, int $total, int $weeklyXp, LeagueTier $tier): string
    {
        if ($rank <= $this->promotionCount && ! $tier->isTop()) {
            return 'promoted';
        }

        if ($this->isInDemotionZone($rank, $total) && $weeklyXp > 0 && ! $tier->isBottom()) {
            return 'demoted';
        }

        return 'stayed';
    }

    private function nextTierFor(int $rank, int $total, int $weeklyXp, LeagueTier $tier): LeagueTier
    {
        return match ($this->resultFor($rank, $total, $weeklyXp, $tier)) {
            'promoted' => $tier->next(),
            'demoted' => $tier->previous(),
            default => $tier,
        };
    }

    /**
     * Düşme bölgesi, kohort küçükse daralır.
     *
     * 8 kişilik bir ligde son 5'i düşürmek, katılanların çoğunu cezalandırmak
     * olurdu — erken dönemde kohortlar dolmadan bu durum sık görülür.
     */
    private function isInDemotionZone(int $rank, int $total): bool
    {
        if ($total <= $this->promotionCount) {
            return false;
        }

        $zone = min($this->demotionCount, intdiv($total, 3));

        return $zone > 0 && $rank > $total - $zone;
    }

    public function promotionCount(): int
    {
        return $this->promotionCount;
    }

    public function demotionCount(): int
    {
        return $this->demotionCount;
    }
}
