<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent\Repository;

use App\Shared\Domain\Learner\LearningStats;
use App\Shared\Domain\Learner\LearningStatsReader;
use Illuminate\Support\Facades\DB;

/**
 * Learning'in dışarıya açtığı özet.
 *
 * Gamification bu modülün tablolarına dokunmaz; rozet değerlendirmesi için
 * gereken sayılar tek sorguda toplanıp DTO olarak verilir.
 */
final class EloquentLearningStatsReader implements LearningStatsReader
{
    public function statsFor(int $userId): LearningStats
    {
        $units = (int) DB::table('user_unit_progress')
            ->where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->count();

        $nodes = (int) DB::table('user_node_progress')
            ->where('user_id', $userId)
            ->where('completed', true)
            ->count();

        $maxLevel = (int) DB::table('user_course_progress')
            ->where('user_id', $userId)
            ->max('level');

        $strongTopics = (int) DB::table('user_topic_stats')
            ->where('user_id', $userId)
            ->where('mastery', 'strong')
            ->count();

        $seconds = (int) DB::table('study_sessions')
            ->where('user_id', $userId)
            ->where('status', 'completed')
            ->selectRaw('coalesce(sum(extract(epoch from (completed_at - started_at))), 0) as total')
            ->value('total');

        return new LearningStats(
            completedUnits: $units,
            completedNodes: $nodes,
            maxCourseLevel: $maxLevel,
            strongTopics: $strongTopics,
            totalStudySeconds: $seconds,
        );
    }
}
