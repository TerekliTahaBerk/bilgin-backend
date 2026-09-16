<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent\Repository;

use App\Modules\Catalog\Domain\Contract\ReviewQueueReader;
use App\Modules\Learning\Infrastructure\Eloquent\Model\ReviewQueueItem;
use App\Shared\Clock\ClockInterface;

/** NullReviewQueueReader'ın yerini alır: "Hızlı Tekrar" artık gerçek yanlışları getirir. */
final readonly class EloquentReviewQueueReader implements ReviewQueueReader
{
    public function __construct(private ClockInterface $clock) {}

    public function dueExerciseIds(int $userId, int $unitId, int $limit): array
    {
        $ids = ReviewQueueItem::query()
            ->where('user_id', $userId)
            ->where('unit_id', $unitId)
            ->where('due_at', '<=', $this->clock->now())
            // En çok takılınan sorular önce: tekrar turu en çok fayda
            // sağlayacağı yerden başlamalı.
            ->orderByDesc('lapses')
            ->orderBy('due_at')
            ->limit($limit)
            ->pluck('exercise_id')
            ->all();

        return array_values(array_map(static fn ($id): int => (int) $id, $ids));
    }
}
