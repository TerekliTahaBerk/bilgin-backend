<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Eloquent\Repository;

use App\Modules\Catalog\Domain\Contract\CourseTopicReader;
use App\Modules\Catalog\Domain\Contract\UnitTopicReader;
use Illuminate\Support\Facades\DB;

final class EloquentUnitTopicReader implements CourseTopicReader, UnitTopicReader
{
    /** @return list<int> */
    public function topicIdsForUnit(int $unitId): array
    {
        $ids = DB::table('unit_topics')
            ->where('unit_id', $unitId)
            ->pluck('topic_id')
            ->all();

        return array_values(array_map(static fn ($id): int => (int) $id, $ids));
    }

    /** @return list<int> */
    public function topicIdsForCourse(int $courseId): array
    {
        $ids = DB::table('unit_topics')
            ->join('units', 'units.id', '=', 'unit_topics.unit_id')
            ->where('units.course_id', $courseId)
            ->where('units.status', 'published')
            ->distinct()
            ->pluck('unit_topics.topic_id')
            ->all();

        return array_values(array_map(static fn ($id): int => (int) $id, $ids));
    }
}
