<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Application\UseCase;

use App\Modules\Gamification\Domain\Badge\BadgeCriterionFactory;
use App\Modules\Gamification\Domain\Badge\BadgeEvaluation;
use App\Modules\Gamification\Domain\Badge\BadgeSnapshot;
use App\Modules\Gamification\Infrastructure\Eloquent\Model\Badge;
use App\Modules\Gamification\Infrastructure\Eloquent\Model\UserBadge;
use App\Modules\Gamification\Infrastructure\Eloquent\Model\UserStat;
use App\Shared\Clock\ClockInterface;
use App\Shared\Domain\Learner\LearningStatsReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Rozetleri değerlendirir ve yeni kazanılanları yazar.
 *
 * Tek bir snapshot üzerinde tüm rozetler değerlendirilir; 20 rozet için
 * 20 sorgu değil, iki okuma yeterli. Yeni kazanılanlar döner ki sonuç
 * ekranı kutlamayı aynı yanıtta gösterebilsin.
 */
final readonly class EvaluateBadges
{
    public function __construct(
        private ClockInterface $clock,
        private BadgeCriterionFactory $criteria,
        private LearningStatsReader $learningStats,
    ) {}

    /** @return list<Badge> yeni kazanılan rozetler */
    public function __invoke(int $userId): array
    {
        $snapshot = $this->snapshotFor($userId);

        $earnedIds = UserBadge::query()->where('user_id', $userId)->pluck('badge_id')->all();

        $newlyEarned = [];

        foreach (Badge::query()->where('is_active', true)->orderBy('sort_order')->get() as $badge) {
            if (in_array($badge->id, $earnedIds, true)) {
                continue;
            }

            if (! $this->evaluate($badge, $snapshot)->earned) {
                continue;
            }

            // insertOrIgnore: eşzamanlı iki oturum aynı rozeti tetiklerse
            // ikincisi sessizce düşer, hata üretmez.
            $inserted = DB::table('user_badges')->insertOrIgnore([
                'user_id' => $userId,
                'badge_id' => $badge->id,
                'earned_at' => $this->clock->now(),
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);

            $inserted > 0 && $newlyEarned[] = $badge;
        }

        return $newlyEarned;
    }

    /**
     * Tüm rozetlerin durumu — kazanılmamışların ilerlemesiyle birlikte.
     *
     * @return list<array{badge: Badge, evaluation: BadgeEvaluation, earnedAt: ?string}>
     */
    public function statusFor(int $userId): array
    {
        $snapshot = $this->snapshotFor($userId);

        $earned = UserBadge::query()
            ->where('user_id', $userId)
            ->pluck('earned_at', 'badge_id');

        $rows = Badge::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Badge $badge): array => [
                'badge' => $badge,
                'evaluation' => $this->evaluate($badge, $snapshot),
                'earnedAt' => $earned->get($badge->id)?->format(DATE_ATOM),
            ])
            ->all();

        return array_values($rows);
    }

    private function evaluate(Badge $badge, BadgeSnapshot $snapshot): BadgeEvaluation
    {
        try {
            return $this->criteria->make($badge->criteria ?? [])->evaluate($snapshot);
        } catch (InvalidArgumentException $e) {
            // Bozuk kriter tanımı tüm rozet ekranını çökertmemeli; o rozet
            // "kazanılmamış" görünür ve sorun log'a düşer.
            Log::warning('Geçersiz rozet kriteri', ['badge' => $badge->code, 'error' => $e->getMessage()]);

            return new BadgeEvaluation(earned: false, current: 0, target: 1);
        }
    }

    private function snapshotFor(int $userId): BadgeSnapshot
    {
        $stats = UserStat::query()->find($userId);
        $learning = $this->learningStats->statsFor($userId);

        return new BadgeSnapshot(
            totalXp: (int) ($stats->total_xp ?? 0),
            level: (int) ($stats->level ?? 1),
            currentStreak: (int) ($stats->current_streak ?? 0),
            longestStreak: (int) ($stats->longest_streak ?? 0),
            totalSessions: (int) ($stats->total_sessions ?? 0),
            perfectSessions: (int) ($stats->perfect_sessions ?? 0),
            totalCorrect: (int) ($stats->total_correct ?? 0),
            completedUnits: $learning->completedUnits,
            completedNodes: $learning->completedNodes,
            maxCourseLevel: $learning->maxCourseLevel,
            strongTopics: $learning->strongTopics,
        );
    }
}
