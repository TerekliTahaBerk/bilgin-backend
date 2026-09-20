<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Eloquent\Repository;

use App\Modules\Catalog\Domain\Contract\ExercisePool;
use App\Modules\Catalog\Domain\Selection\ExerciseRef;
use App\Modules\Catalog\Domain\Selection\PoolCriteria;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use App\Shared\Domain\Enum\PublishStatus;
use Illuminate\Database\Eloquent\Builder;

final class EloquentExercisePool implements ExercisePool
{
    /**
     * Yayın doğrulamasında aday sayılan, üniteye ait soru durumları.
     *
     * Arşiv bilinçli olarak dışarıda: arşivlenmiş bir soru yayın sayısına
     * katılsaydı, yayın kapısı öğrenciye asla gösterilmeyecek soruları
     * sayarak geçerdi.
     *
     * @var list<PublishStatus>
     */
    private const PUBLICATION_CANDIDATE_STATUSES = [
        PublishStatus::Draft,
        PublishStatus::Review,
        PublishStatus::Published,
    ];

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
    public function findSelectableByIds(array $ids, ?int $publicationUnitId = null): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = Exercise::query()
            ->where(fn (Builder $q) => $this->selectable($q, $publicationUnitId))
            ->whereIn('id', $ids)
            ->get(['id', 'uuid', 'type', 'difficulty', 'topic_id', 'version']);

        return $this->toRefs($rows);
    }

    /** @return Builder<Exercise> */
    private function query(PoolCriteria $criteria): Builder
    {
        return Exercise::query()
            // Durum koşulu kendi parantezinde duruyor: OR'lu aday koşulunun
            // konu/zorluk filtrelerini yutmaması buna bağlı.
            ->where(fn (Builder $q) => $this->selectable($q, $criteria->publicationUnitId))
            ->when($criteria->topicIds !== [], fn (Builder $q) => $q->whereIn('topic_id', $criteria->topicIds))
            ->whereBetween('difficulty', [$criteria->difficultyMin, $criteria->difficultyMax])
            ->when($criteria->types !== [], fn (Builder $q) => $q->whereIn('type', $criteria->types))
            ->when($criteria->excludeExerciseIds !== [], fn (Builder $q) => $q->whereNotIn('id', $criteria->excludeExerciseIds))
            ->forScope($criteria->scope);
    }

    /**
     * Seçilebilirlik koşulu: yayındakiler — yayın doğrulamasında ek olarak
     * yayınlanmaya çalışılan ünitenin arşivlenmemiş soruları.
     *
     * @param  Builder<Exercise>  $query
     * @return Builder<Exercise>
     */
    private function selectable(Builder $query, ?int $publicationUnitId): Builder
    {
        $query->where('status', PublishStatus::Published);

        if ($publicationUnitId !== null) {
            $query->orWhere(function (Builder $owned) use ($publicationUnitId): void {
                $owned->where('owner_unit_id', $publicationUnitId)
                    ->whereIn('status', self::PUBLICATION_CANDIDATE_STATUSES);
            });
        }

        return $query;
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
