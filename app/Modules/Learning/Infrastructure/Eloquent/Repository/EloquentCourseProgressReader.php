<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent\Repository;

use App\Shared\Domain\Learner\CourseProgress;
use App\Shared\Domain\Learner\CourseProgressReader;
use Illuminate\Support\Facades\DB;

/**
 * Ders ilerlemesinin Learning dışına açılan okuması.
 *
 * Curriculum ders listesini kuruyor ama `user_course_progress` bu modülün
 * tablosu. Tabloyu oradan sorgulatmak, iki modülü şemaya bağlardı.
 */
final class EloquentCourseProgressReader implements CourseProgressReader
{
    public function forCourses(int $userId, array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        /** @var array<int, CourseProgress> $result */
        $result = [];

        // Tek sorgu, ders başına bir tane değil: ders listesi en sık açılan
        // ekran ve yirmiye yakın ders çiziyor.
        $rows = DB::table('user_course_progress')
            ->where('user_id', $userId)
            ->whereIn('course_id', $courseIds)
            ->get(['course_id', 'level', 'xp', 'completed_units', 'total_units']);

        foreach ($rows as $row) {
            $result[(int) $row->course_id] = new CourseProgress(
                level: (int) $row->level,
                xp: (int) $row->xp,
                completedUnits: (int) $row->completed_units,
                totalUnits: (int) $row->total_units,
            );
        }

        return $result;
    }
}
