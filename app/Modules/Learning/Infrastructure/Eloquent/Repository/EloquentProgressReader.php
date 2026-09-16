<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent\Repository;

use App\Modules\Catalog\Domain\Contract\ProgressReader;
use App\Modules\Catalog\Domain\Unlock\ProgressSnapshot;
use App\Modules\Learning\Infrastructure\Eloquent\Model\UserNodeProgress;
use App\Modules\Learning\Infrastructure\Eloquent\Model\UserUnitProgress;

/**
 * NullProgressReader'ın yerini alır.
 *
 * Tüm ders TEK sorguda okunur: kilit kuralları node başına sorgu çalıştırsaydı
 * yol ekranı N+1'e düşerdi ve bu ekran uygulamanın her açılışında görülüyor.
 */
final class EloquentProgressReader implements ProgressReader
{
    public function snapshotForCourse(int $userId, int $courseId): ProgressSnapshot
    {
        $nodes = UserNodeProgress::query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->get(['unit_node_id', 'completed', 'best_accuracy']);

        $completedNodes = [];
        $accuracy = [];

        foreach ($nodes as $row) {
            $completedNodes[(int) $row->unit_node_id] = (bool) $row->completed;
            $accuracy[(int) $row->unit_node_id] = (int) $row->best_accuracy;
        }

        $completedUnits = UserUnitProgress::query()
            ->where('user_id', $userId)
            ->where('course_id', $courseId)
            ->whereNotNull('completed_at')
            ->pluck('unit_id')
            ->mapWithKeys(static fn ($id): array => [(int) $id => true])
            ->all();

        return new ProgressSnapshot($completedNodes, $accuracy, $completedUnits);
    }
}
