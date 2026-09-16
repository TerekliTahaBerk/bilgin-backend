<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Eloquent\Repository;

use App\Modules\Catalog\Domain\Contract\ExercisePool;
use App\Modules\Catalog\Domain\Selection\ExerciseRef;
use App\Modules\Catalog\Domain\Selection\PoolCriteria;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use Illuminate\Database\Eloquent\Builder;

final class EloquentExercisePool implements ExercisePool
{
    public function count(PoolCriteria $criteria): int
    {
        return $this->query($criteria)->count();
    }

    /** @return list<ExerciseRef> */
    public function pick(PoolCriteria $criteria, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $rows = $this->query($criteria)
            ->inRandomOrder()
            ->limit($limit)
            ->get(['id', 'uuid', 'type', 'difficulty', 'topic_id', 'version']);

        return $this->toRefs($rows);
    }

    /**
     * @param  list<int>  $ids
     * @return list<ExerciseRef>
     */
    public function findPublishedByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = Exercise::query()
            ->published()
            ->whereIn('id', $ids)
            ->get(['id', 'uuid', 'type', 'difficulty', 'topic_id', 'version']);

        return $this->toRefs($rows);
    }

    /** @return Builder<Exercise> */
    private function query(PoolCriteria $criteria): Builder
    {
        return Exercise::query()
            ->published()
            ->when($criteria->topicIds !== [], fn (Builder $q) => $q->whereIn('topic_id', $criteria->topicIds))
            ->whereBetween('difficulty', [$criteria->difficultyMin, $criteria->difficultyMax])
            ->when($criteria->types !== [], fn (Builder $q) => $q->whereIn('type', $criteria->types))
            ->when($criteria->excludeExerciseIds !== [], fn (Builder $q) => $q->whereNotIn('id', $criteria->excludeExerciseIds))
            ->forScope($criteria->scope);
    }

    /**
     * @param  iterable<int, Exercise>  $rows
     * @return list<ExerciseRef>
     */
    private function toRefs(iterable $rows): array
    {
        $refs = [];

        foreach ($rows as $row) {
            $refs[] = new ExerciseRef(
                id: (int) $row->id,
                uuid: (string) $row->uuid,
                type: $row->getRawOriginal('type'),
                difficulty: (int) $row->difficulty,
                topicId: (int) $row->topic_id,
                version: (int) $row->version,
            );
        }

        return $refs;
    }
}
