<?php

declare(strict_types=1);

namespace App\Modules\League\Application\UseCase;

use App\Modules\League\Domain\LeagueWeek;
use App\Modules\League\Infrastructure\Eloquent\Model\LeagueMembership;
use App\Shared\Clock\ClockInterface;
use Illuminate\Support\Facades\DB;

/**
 * Lig ekranının verisi.
 *
 * Sıralama Redis yerine indeksli tek sorgudan geliyor. Kohortlar 30 kişilik
 * olduğu için ORDER BY weekly_xp ile sıralamak milisaniyeler sürüyor; Redis
 * ZSET'i ancak yazma çekişmesi gerçekten görülürse gerekir. Şimdiden eklemek,
 * kazanılmamış bir karmaşıklık olurdu.
 */
final readonly class GetStandings
{
    public function __construct(private ClockInterface $clock) {}

    public function __invoke(int $userId): ?StandingsView
    {
        $week = LeagueWeek::containing($this->clock->now());

        $membership = LeagueMembership::query()
            ->with('league')
            ->where('user_id', $userId)
            ->where('week_start', $week->startsOn)
            ->first();

        // Bu hafta hiç XP kazanmamış kullanıcı henüz bir ligde değil.
        if ($membership === null) {
            return null;
        }

        $rows = DB::table('league_memberships as lm')
            ->join('users as u', 'u.id', '=', 'lm.user_id')
            ->leftJoin('user_stats as s', 's.user_id', '=', 'lm.user_id')
            ->where('lm.league_id', $membership->league_id)
            ->orderByDesc('lm.weekly_xp')
            ->orderBy('lm.id')
            ->get(['lm.user_id', 'lm.weekly_xp', 'u.name', 'u.avatar_key', 's.current_streak']);

        $members = [];
        $myRank = 0;

        foreach ($rows as $index => $row) {
            $rank = $index + 1;

            if ((int) $row->user_id === $userId) {
                $myRank = $rank;
            }

            $members[] = new StandingMember(
                rank: $rank,
                userId: (int) $row->user_id,
                name: $row->name,
                avatarKey: $row->avatar_key,
                weeklyXp: (int) $row->weekly_xp,
                streak: (int) ($row->current_streak ?? 0),
                isMe: (int) $row->user_id === $userId,
            );
        }

        return new StandingsView(
            tier: $membership->league->tier,
            weekStart: $week->startsOn,
            endsAt: $week->endsAt(),
            myRank: $myRank,
            members: $members,
            promotionCount: (int) config('tekrarla.league.promotion_count'),
            demotionCount: (int) config('tekrarla.league.demotion_count'),
        );
    }
}
