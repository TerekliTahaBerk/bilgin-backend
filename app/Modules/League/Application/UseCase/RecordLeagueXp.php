<?php

declare(strict_types=1);

namespace App\Modules\League\Application\UseCase;

use App\Modules\League\Domain\Enum\LeagueTier;
use App\Modules\League\Domain\LeagueWeek;
use App\Modules\League\Infrastructure\Eloquent\Model\League;
use App\Modules\League\Infrastructure\Eloquent\Model\LeagueMembership;
use App\Shared\Domain\Event\XpAwarded;
use Illuminate\Support\Facades\DB;

/**
 * XP kazanıldığında lig kaydını günceller.
 *
 * Lige katılım TEMBEL: kullanıcı haftanın ilk XP'sini kazandığında atanır.
 * Herkesi hafta başında bir lige koymak, hiç oynamayan kullanıcılarla
 * kohortları şişirir ve oynayanların sıralaması anlamsızlaşır — 30 kişilik
 * ligin 25'i sıfır XP'de durursa yarış diye bir şey kalmaz.
 */
final readonly class RecordLeagueXp
{
    // Zaman enjekte edilmiyor: hangi haftaya yazılacağını olayın KENDİ
    // zaman damgası belirler. "Şimdi"ye bakmak, kuyrukta gecikmiş bir
    // olayı yanlış haftaya yazardı.
    public function __invoke(XpAwarded $event): void
    {
        if (! $event->countsForLeague || $event->amount <= 0) {
            return;
        }

        $week = LeagueWeek::containing($event->awardedAt);

        DB::transaction(function () use ($event, $week): void {
            $membership = LeagueMembership::query()
                ->where('user_id', $event->userId)
                ->where('week_start', $week->startsOn)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                $membership = $this->join($event->userId, $week);
            }

            $membership->increment('weekly_xp', $event->amount);
        });
    }

    /** Kullanıcıyı kademesine uygun, dolmamış bir kohorta yerleştirir. */
    private function join(int $userId, LeagueWeek $week): LeagueMembership
    {
        $tier = $this->tierFor($userId, $week);

        $league = League::query()
            ->where('tier', $tier)
            ->where('week_start', $week->startsOn)
            ->where('status', 'active')
            ->whereColumn('member_count', '<', 'capacity')
            // En eski dolmamış kohort: kullanıcılar dağılmak yerine
            // toplansın ki ligler dolu ve yarış canlı olsun.
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($league === null) {
            $league = League::query()->create([
                'tier' => $tier,
                'week_start' => $week->startsOn,
                'capacity' => (int) config('tekrarla.league.cohort_size'),
            ]);
        }

        $league->increment('member_count');

        return LeagueMembership::query()->create([
            'league_id' => $league->id,
            'user_id' => $userId,
            'week_start' => $week->startsOn,
            'weekly_xp' => 0,
        ]);
    }

    /**
     * Kullanıcının bu haftaki kademesi.
     *
     * Geçen haftanın sonucu belirler; hiç ligde bulunmamışsa bronzdan başlar.
     * Yeni hesapların bronzdan başlaması çoklu hesapla üst lige sızmayı da
     * anlamsız kılar.
     */
    private function tierFor(int $userId, LeagueWeek $week): LeagueTier
    {
        $previous = LeagueMembership::query()
            ->with('league')
            ->where('user_id', $userId)
            ->where('week_start', $week->previous()->startsOn)
            ->first();

        if ($previous === null) {
            return LeagueTier::Bronze;
        }

        return match ($previous->result) {
            'promoted' => $previous->league->tier->next(),
            'demoted' => $previous->league->tier->previous(),
            default => $previous->league->tier,
        };
    }
}
