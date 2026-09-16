<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Domain\Badge;

/**
 * Rozet değerlendirmesinin tek girdisi.
 *
 * Tüm kriterler bu nesneye bakar; hiçbiri veritabanına gitmez. Sonuç:
 * 20 rozetin değerlendirilmesi tek okuma, N+1 sorgu değil — ve kriter
 * mantığı veritabanısız test edilebiliyor.
 */
final readonly class BadgeSnapshot
{
    public function __construct(
        public int $totalXp = 0,
        public int $level = 1,
        public int $currentStreak = 0,
        public int $longestStreak = 0,
        public int $totalSessions = 0,
        public int $perfectSessions = 0,
        public int $totalCorrect = 0,
        public int $completedUnits = 0,
        public int $completedNodes = 0,
        public int $maxCourseLevel = 0,
        public int $strongTopics = 0,
    ) {}

    public function valueFor(string $metric): int
    {
        return match ($metric) {
            'total_xp' => $this->totalXp,
            'level' => $this->level,
            'current_streak' => $this->currentStreak,
            'longest_streak' => $this->longestStreak,
            'total_sessions' => $this->totalSessions,
            'perfect_sessions' => $this->perfectSessions,
            'total_correct' => $this->totalCorrect,
            'completed_units' => $this->completedUnits,
            'completed_nodes' => $this->completedNodes,
            'max_course_level' => $this->maxCourseLevel,
            'strong_topics' => $this->strongTopics,
            default => 0,
        };
    }
}
