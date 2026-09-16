<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Application\UseCase;

use App\Modules\Gamification\Application\DTO\SessionXpContext;
use App\Modules\Gamification\Domain\Streak\StreakState;
use App\Modules\Gamification\Domain\Streak\StreakTracker;
use App\Modules\Gamification\Domain\Xp\LevelCurve;
use App\Modules\Gamification\Domain\Xp\SessionOutcome;
use App\Modules\Gamification\Domain\Xp\XpCalculator;
use App\Modules\Gamification\Infrastructure\Eloquent\Model\UserStat;
use App\Modules\Gamification\Infrastructure\Eloquent\Model\XpLedgerEntry;
use App\Shared\Clock\ClockInterface;
use App\Shared\Domain\Event\XpAwarded;
use App\Shared\Domain\Learner\LearnerProfileReader;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Oturum XP'sini yazar, level ve seriyi günceller.
 *
 * XP defteri tek doğruluk kaynağıdır: unique(source_type, source_id) sayesinde
 * aynı oturum ikinci kez XP üretemez. user_stats.total_xp defterden TÜRETİLİR,
 * bağımsız sayaç değildir — tutarsızlık şüphesinde yeniden inşa edilebilir.
 */
final readonly class AwardSessionXp
{
    public function __construct(
        private ClockInterface $clock,
        private XpCalculator $calculator,
        private LevelCurve $levels,
        private StreakTracker $streaks,
        private LearnerProfileReader $learners,
    ) {}

    public function __invoke(
        SessionXpContext $session,
        int $baseXp,
        int $accuracy,
        bool $isPerfect,
        bool $isFirstCompletion,
        bool $meetsThreshold,
    ): XpOutcome {
        $userId = $session->userId;

        $award = $this->calculator->calculate(new SessionOutcome(
            baseXp: $baseXp,
            accuracy: $accuracy,
            isPerfect: $isPerfect,
            isFirstCompletion: $isFirstCompletion,
            isReplay: $session->isReplay,
            meetsCompletionThreshold: $meetsThreshold,
        ));

        // Varsayılanlar modelin $attributes'ında; burada tekrarlanmaz.
        $stat = UserStat::query()->firstOrCreate(['user_id' => $userId]);

        /*
         | insertOrIgnore, unique ihlalini yakalamak yerine.
         |
         | Bu çağrı CompleteSession'ın transaction'ı içinde. Postgres'te
         | başarısız bir INSERT transaction'ı komple iptal eder; "dene ve
         | yakala" deseni çift XP'yi önlemek yerine turun tamamlanmasını
         | 500 ile düşürürdü.
         */
        DB::table('xp_ledger')->insertOrIgnore([
            'user_id' => $userId,
            'amount' => $award->total,
            'source_type' => 'session',
            'source_id' => $session->sessionId,
            'course_id' => $session->courseId,
            'counts_for_league' => true,
            'breakdown' => json_encode($award->breakdown),
            'awarded_at' => $this->clock->now(),
        ]);

        $levelBefore = (int) $stat->level;

        // Toplam defterden yeniden hesaplanır — bağımsız sayaç sürüklenmez.
        $totalXp = (int) XpLedgerEntry::query()->where('user_id', $userId)->sum('amount');
        $levelAfter = $this->levels->levelFor($totalXp);

        $timezone = $this->learners->for($userId)->timezone;
        $streak = $this->extendStreak($stat, $userId);

        $stat->fill([
            'total_xp' => $totalXp,
            'level' => $levelAfter,
            'current_streak' => $streak->current,
            'longest_streak' => $streak->longest,
            'last_study_date' => $streak->lastStudyDate,
            'total_sessions' => (int) $stat->total_sessions + 1,
            'perfect_sessions' => (int) $stat->perfect_sessions + ($isPerfect ? 1 : 0),
            'total_correct' => (int) $stat->total_correct + $session->correctCount,
        ])->save();

        /*
         | Olay yayınlanıyor — Gamification lig diye bir şey olduğunu BİLMEZ.
         | Lig modülü kaldırılsa bu dosyada tek satır değişmez.
         */
        Event::dispatch(new XpAwarded(
            userId: $userId,
            amount: $award->total,
            sourceType: 'session',
            sourceId: $session->sessionId,
            countsForLeague: true,
            timezone: $timezone,
            awardedAt: $this->clock->now(),
        ));

        return new XpOutcome(
            award: $award,
            levelBefore: $levelBefore,
            levelAfter: $levelAfter,
            totalXp: $totalXp,
            nextLevelXp: $this->levels->nextLevelXp($totalXp),
            streak: $streak,
        );
    }

    /**
     * Seri, kullanıcının YEREL gününe göre uzatılır.
     *
     * İstanbul'da gece 02:00'de çalışan öğrenci için UTC'de tarih çoktan
     * değişmiştir; UTC'ye göre hesaplasaydık serisi haksız yere kırılırdı.
     */
    private function extendStreak(UserStat $stat, int $userId): StreakState
    {
        $timezone = $this->learners->for($userId)->timezone;
        $localNow = $this->clock->now()->setTimezone(new DateTimeZone($timezone));

        $current = new StreakState(
            current: (int) $stat->current_streak,
            longest: (int) $stat->longest_streak,
            lastStudyDate: $stat->last_study_date?->format('Y-m-d'),
        );

        return $this->streaks->extend($current, $localNow);
    }

    /** Defterden toplam XP — destek ekranı ve tutarlılık kontrolü için. */
    public function totalXpFor(int $userId): int
    {
        return (int) DB::table('xp_ledger')->where('user_id', $userId)->sum('amount');
    }
}
