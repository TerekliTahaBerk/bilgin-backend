<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Contract;

use App\Modules\Catalog\Domain\Selection\ExerciseRef;
use App\Modules\Catalog\Domain\Selection\PoolCriteria;

/**
 * Soru havuzuna erişim. Domain yalnızca bu arayüzü tanır; Eloquent
 * implementasyonu Infrastructure'dadır (DIP).
 */
interface ExercisePool
{
    public function count(PoolCriteria $criteria): int;

    /**
     * Ölçüte uyan sorulardan rastgele $limit adet döner.
     *
     * @return list<ExerciseRef>
     */
    public function pick(PoolCriteria $criteria, int $limit): array;

    /**
     * @param  list<int>  $ids
     * @return list<ExerciseRef>
     */
    public function findPublishedByIds(array $ids): array;
}
