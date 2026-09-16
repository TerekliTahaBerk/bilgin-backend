<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Http\Controller\Api\V1;

use App\Modules\Gamification\Application\UseCase\EvaluateBadges;
use App\Modules\Gamification\Domain\Xp\LevelCurve;
use App\Modules\Gamification\Infrastructure\Eloquent\Model\UserStat;
use App\Shared\Domain\Learner\LearningStatsReader;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfileController extends ApiController
{
    /** GET /v1/me/stats — profil ekranının üst bloğu. */
    public function stats(
        Request $request,
        LevelCurve $levels,
        LearningStatsReader $learning,
    ): JsonResponse {
        $userId = $this->userId($request);

        $stats = UserStat::query()->find($userId);
        $totalXp = (int) ($stats->total_xp ?? 0);
        $level = (int) ($stats->level ?? 1);
        $learningStats = $learning->statsFor($userId);

        return ApiResponse::data([
            'level' => [
                'current' => $level,
                'total_xp' => $totalXp,
                'level_start_xp' => $levels->xpForLevel($level),
                'next_level_xp' => $levels->nextLevelXp($totalXp),
            ],
            'streak' => [
                'current' => (int) ($stats->current_streak ?? 0),
                'longest' => (int) ($stats->longest_streak ?? 0),
                'last_study_date' => $stats?->last_study_date?->format('Y-m-d'),
            ],
            'activity' => [
                'total_sessions' => (int) ($stats->total_sessions ?? 0),
                'perfect_sessions' => (int) ($stats->perfect_sessions ?? 0),
                'total_correct' => (int) ($stats->total_correct ?? 0),
                'completed_units' => $learningStats->completedUnits,
                'completed_nodes' => $learningStats->completedNodes,
                'study_minutes' => intdiv($learningStats->totalStudySeconds, 60),
            ],
        ]);
    }

    /** GET /v1/me/badges — profil ekranındaki rozet ızgarası. */
    public function badges(Request $request, EvaluateBadges $badges): JsonResponse
    {
        $items = array_map(
            static fn (array $row): array => array_filter([
                'code' => $row['badge']->code,
                'name' => $row['badge']->name,
                'description' => $row['badge']->description,
                'icon' => $row['badge']->icon,
                'tier' => $row['badge']->tier,
                'earned' => $row['earnedAt'] !== null,
                'earned_at' => $row['earnedAt'],
                // Kilitli rozette ilerleme: "100 doğru" rozetinin 73'te
                // olduğunu görmek, gri bir ikondan çok daha motive edici.
                'progress' => $row['earnedAt'] === null ? [
                    'current' => $row['evaluation']->current,
                    'target' => $row['evaluation']->target,
                    'percent' => $row['evaluation']->percent(),
                ] : null,
            ], static fn ($v): bool => $v !== null),
            $badges->statusFor($this->userId($request)),
        );

        return ApiResponse::data([
            'earned_count' => count(array_filter($items, static fn (array $b): bool => $b['earned'])),
            'total_count' => count($items),
            'badges' => $items,
        ]);
    }
}
