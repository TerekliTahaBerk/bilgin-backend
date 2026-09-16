<?php

declare(strict_types=1);

namespace App\Modules\League\Application\UseCase;

use App\Modules\League\Domain\Enum\LeagueTier;
use DateTimeImmutable;

final readonly class StandingsView
{
    /** @param  list<StandingMember>  $members */
    public function __construct(
        public LeagueTier $tier,
        public string $weekStart,
        public DateTimeImmutable $endsAt,
        public int $myRank,
        public array $members,
        public int $promotionCount,
        public int $demotionCount,
    ) {}

    /**
     * Yükselme bölgesine kaç XP kaldı? Tasarımdaki "İlk 5'e 700 XP".
     *
     * Zaten bölgedeyse null — o zaman gösterilecek mesaj farklı.
     */
    public function xpToPromotion(): ?int
    {
        if ($this->myRank === 0 || $this->myRank <= $this->promotionCount) {
            return null;
        }

        $threshold = $this->members[$this->promotionCount - 1] ?? null;
        $me = $this->members[$this->myRank - 1] ?? null;

        if ($threshold === null || $me === null) {
            return null;
        }

        // +1: eşitlikte önce katılan üstte kaldığı için geçmek bir XP fazlası ister.
        return max(0, $threshold->weeklyXp - $me->weeklyXp + 1);
    }

    /** Düşme bölgesi kohort küçükse daralır; ekranda doğru sınır gösterilmeli. */
    public function demotionZoneStartsAt(): ?int
    {
        $total = count($this->members);

        if ($total <= $this->promotionCount) {
            return null;
        }

        $zone = min($this->demotionCount, intdiv($total, 3));

        return $zone > 0 ? $total - $zone + 1 : null;
    }
}
