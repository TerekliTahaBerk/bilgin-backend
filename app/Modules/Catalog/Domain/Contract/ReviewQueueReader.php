<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Contract;

/**
 * Kullanıcının tekrar kuyruğu. Gerçek implementasyonu Learning modülünde
 * olacak; Catalog yalnızca bu arayüzü tanır, ters yönde bağımlılık kurmaz.
 */
interface ReviewQueueReader
{
    /**
     * Tekrar zamanı gelmiş soru id'leri.
     *
     * @return list<int>
     */
    public function dueExerciseIds(int $userId, int $unitId, int $limit): array;
}
