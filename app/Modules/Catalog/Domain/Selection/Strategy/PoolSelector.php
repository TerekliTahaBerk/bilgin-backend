<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Selection\Strategy;

use App\Modules\Catalog\Domain\Contract\ExercisePool;
use App\Modules\Catalog\Domain\Enum\SelectionMode;
use App\Modules\Catalog\Domain\Selection\ExerciseRef;
use App\Modules\Catalog\Domain\Selection\ExerciseSelector;
use App\Modules\Catalog\Domain\Selection\PoolCriteria;
use App\Modules\Catalog\Domain\Selection\SelectionContext;
use App\Modules\Catalog\Domain\Selection\SelectionResult;
use App\Modules\Catalog\Domain\Selection\SelectionRule;

/**
 * Varsayılan mod: kuralda tarif edilen havuzdan rastgele soru çeker.
 *
 * Havuz yetersiz kalırsa kuralın fallback'i uygulanır. Yetersizlik sessizce
 * yutulmaz — SelectionResult eksiği raporlar, yayın kapısı bunu hata sayar.
 */
final readonly class PoolSelector implements ExerciseSelector
{
    public function __construct(private ExercisePool $pool) {}

    public function mode(): SelectionMode
    {
        return SelectionMode::Pool;
    }

    public function select(SelectionRule $rule, SelectionContext $context): SelectionResult
    {
        $criteria = $this->criteriaFor($rule, $context);

        $picked = $this->pick($criteria, $rule->count, $context);
        $relaxed = false;

        if (count($picked) < $rule->count && $rule->allowsRelaxing()) {
            $relaxed = true;
            $picked = $this->topUp($picked, $criteria->relaxed(), $rule->count, $context);

            if (count($picked) < $rule->count) {
                $picked = $this->topUp($picked, $criteria->relaxed()->withoutTypeFilter(), $rule->count, $context);
            }
        }

        return new SelectionResult($picked, $rule->count, $relaxed);
    }

    private function criteriaFor(SelectionRule $rule, SelectionContext $context): PoolCriteria
    {
        $topicIds = $rule->inheritTopicsFromUnit || $rule->topicIds === []
            ? $context->unitTopicIds
            : $rule->topicIds;

        return new PoolCriteria(
            topicIds: $topicIds,
            scope: $rule->scope ?? $context->courseScope,
            difficultyMin: $rule->difficultyMin,
            difficultyMax: $rule->difficultyMax,
            types: $rule->types,
            excludeExerciseIds: $context->excludeExerciseIds,
        );
    }

    /**
     * @param  list<ExerciseRef>  $picked
     * @return list<ExerciseRef>
     */
    private function topUp(array $picked, PoolCriteria $criteria, int $target, SelectionContext $context): array
    {
        $have = array_column($picked, 'id');
        $extra = $this->pick(
            new PoolCriteria(
                $criteria->topicIds,
                $criteria->scope,
                $criteria->difficultyMin,
                $criteria->difficultyMax,
                $criteria->types,
                [...$criteria->excludeExerciseIds, ...$have],
            ),
            $target - count($picked),
            $context,
        );

        return [...$picked, ...$extra];
    }

    /** @return list<ExerciseRef> */
    private function pick(PoolCriteria $criteria, int $limit, SelectionContext $context): array
    {
        return $context->publicationValidation
            ? $this->pool->pickForPublication($criteria, $limit, $context->unitId)
            : $this->pool->pick($criteria, $limit);
    }
}
