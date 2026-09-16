<?php

declare(strict_types=1);

namespace App\Modules\League\Application\UseCase;

use App\Modules\League\Domain\LeagueWeek;
use App\Modules\League\Domain\Promotion\PromotionRule;
use App\Modules\League\Domain\Promotion\StandingEntry;
use App\Modules\League\Infrastructure\Eloquent\Model\League;
use App\Modules\League\Infrastructure\Eloquent\Model\LeagueMembership;
use App\Shared\Clock\ClockInterface;
use Illuminate\Support\Facades\DB;

/**
 * Haftalık kapanış: sıralamayı kesinleştirir, terfi ve düşmeleri yazar.
 *
 * İDEMPOTENT ve parça parça: `status = closed` kontrolü sayesinde yeniden
 * çalıştırmak sonucu değiştirmez, kohortlar tek tek işlenir. Haftalık bir
 * job'ın yarısında düşen sunucu, bir sonraki denemede kaldığı yerden devam
 * etmeli — aksi hâlde bazı kullanıcılar terfi eder, bazıları asılı kalır.
 */
final readonly class CloseLeagueWeek
{
    public function __construct(
        private ClockInterface $clock,
        private PromotionRule $rule,
    ) {}

    public function __invoke(?string $weekStart = null): CloseReport
    {
        // Varsayılan: bir önceki hafta. Job Pazartesi 00:00'da çalışır ve
        // o an biten haftayı kapatır.
        $week = $weekStart ?? LeagueWeek::containing($this->clock->now())->previous()->startsOn;

        $leagues = League::query()
            ->where('week_start', $week)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        $closed = 0;
        $promoted = 0;
        $demoted = 0;

        foreach ($leagues as $league) {
            $result = $this->closeLeague($league);

            $closed++;
            $promoted += $result['promoted'];
            $demoted += $result['demoted'];
        }

        return new CloseReport($week, $closed, $promoted, $demoted);
    }

    /** @return array{promoted: int, demoted: int} */
    private function closeLeague(League $league): array
    {
        return DB::transaction(function () use ($league): array {
            $fresh = League::query()->lockForUpdate()->find($league->id);

            // Başka bir çalıştırma önce davranmış olabilir.
            if ($fresh === null || $fresh->status !== 'active') {
                return ['promoted' => 0, 'demoted' => 0];
            }

            $memberships = $fresh->memberships()
                // Eşitlikte önce katılan üstte: sonradan katılıp aynı XP'yle
                // birini geçmek adil değil.
                ->orderByDesc('weekly_xp')
                ->orderBy('id')
                ->get();

            $standings = $memberships
                ->map(fn (LeagueMembership $m): StandingEntry => new StandingEntry(
                    (int) $m->user_id,
                    (int) $m->weekly_xp,
                ))
                ->all();

            $outcomes = $this->rule->decide(array_values($standings), $fresh->tier);

            $promoted = 0;
            $demoted = 0;

            // Sonuçlar üyeliklerle AYNI sırada; id üzerinden eşleştirerek
            // indeks kaymasına yer bırakmıyoruz.
            $byUserId = $memberships->keyBy('user_id');

            foreach ($outcomes as $outcome) {
                $membership = $byUserId->get($outcome->userId);

                if (! $membership instanceof LeagueMembership) {
                    continue;
                }

                $membership->update([
                    'final_rank' => $outcome->rank,
                    'result' => $outcome->result,
                ]);

                $outcome->result === 'promoted' && $promoted++;
                $outcome->result === 'demoted' && $demoted++;
            }

            $fresh->update(['status' => 'closed', 'closed_at' => $this->clock->now()]);

            return ['promoted' => $promoted, 'demoted' => $demoted];
        });
    }
}
