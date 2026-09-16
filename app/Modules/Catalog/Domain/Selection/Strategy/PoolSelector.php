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

        $picked = $this->pool->pick($criteria, $rule->count);
        $relaxed = false;

        if (count($picked) < $rule->count && $rule->allowsRelaxing()) {
            $relaxed = true;
            $picked = $this->topUp($picked, $criteria->relaxed(), $rule->count);

            if (count($picked) < $rule->count) {
                $picked = $this->topUp($picked, $criteria->relaxed()->withoutTypeFilter(), $rule->count);
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
    private function topUp(array $picked, PoolCriteria $criteria, int $target): array
    {
        $have = array_column($picked, 'id');
        $extra = $this->pool->pick(
            new PoolCriteria(
                $criteria->topicIds,
                $criteria->scope,
                $criteria->difficultyMin,
                $criteria->difficultyMax,
                $criteria->types,
                [...$criteria->excludeExerciseIds, ...$have],
            ),
            $target - count($picked),
        );

        return [...$picked, ...$extra];
    }
}
