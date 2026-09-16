<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Selection\Strategy;

use App\Modules\Catalog\Domain\Contract\ExercisePool;
use App\Modules\Catalog\Domain\Contract\ReviewQueueReader;
use App\Modules\Catalog\Domain\Enum\SelectionMode;
use App\Modules\Catalog\Domain\Selection\ExerciseSelector;
use App\Modules\Catalog\Domain\Selection\PoolCriteria;
use App\Modules\Catalog\Domain\Selection\SelectionContext;
use App\Modules\Catalog\Domain\Selection\SelectionResult;
use App\Modules\Catalog\Domain\Selection\SelectionRule;

/**
 * "Hızlı Tekrar" node'u: önce kullanıcının yanlışları, eksik kalırsa ünite havuzu.
 *
 * Havuza düşme bir yedek plan değil, tasarımın parçası: yeni kullanıcının
 * kuyruğu boştur ve o da tekrar turu görebilmelidir. Aynı sebeple kullanıcısız
 * bağlamda (yayın kapısı) da doğru çalışır.
 */
final readonly class ReviewQueueSelector implements ExerciseSelector
{
    public function __construct(
        private ReviewQueueReader $queue,
        private ExercisePool $pool,
    ) {}

    public function mode(): SelectionMode
    {
        return SelectionMode::ReviewQueue;
    }

    public function select(SelectionRule $rule, SelectionContext $context): SelectionResult
    {
        $dueIds = $context->userId !== null
            ? $this->queue->dueExerciseIds($context->userId, $context->unitId, $rule->count)
            : [];

        $selected = $this->pool->findPublishedByIds($dueIds);

        if (count($selected) < $rule->count) {
            $selected = [...$selected, ...$this->pool->pick(
                new PoolCriteria(
                    topicIds: $context->unitTopicIds,
                    scope: $context->courseScope,
                    excludeExerciseIds: [...$context->excludeExerciseIds, ...array_column($selected, 'id')],
                ),
                $rule->count - count($selected),
            )];
        }

        return new SelectionResult($selected, $rule->count);
    }
}
