<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Eloquent\Repository;

use App\Modules\Catalog\Domain\Contract\ReviewQueueReader;

/**
 * Learning modülü gelene kadarki geçici bağlama. Boş kuyruk döner; bu
 * ReviewQueueSelector'ın havuza düşmesini sağlar — yani "Hızlı Tekrar"
 * her zaman çalışır, yanlışı olmayan kullanıcıda bile.
 *
 * Bilinçli olarak null yerine boş dizi: çağıran taraf hiçbir zaman
 * "kuyruk var mı" diye sormak zorunda kalmaz.
 */
final class NullReviewQueueReader implements ReviewQueueReader
{
    /** @return list<int> */
    public function dueExerciseIds(int $userId, int $unitId, int $limit): array
    {
        return [];
    }
}
