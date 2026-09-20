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
     * Runtime havuzunu değiştirmeden, yayın kapısına global yayınlanmış
     * sorularla birlikte hedef ünitenin arşivlenmemiş adaylarını verir.
     *
     * @return list<ExerciseRef>
     */
    public function pickForPublication(PoolCriteria $criteria, int $limit, int $unitId): array;

    /**
     * @param  list<int>  $ids
     * @return list<ExerciseRef>
     */
    public function findPublishedByIds(array $ids): array;

    /**
     * @param  list<int>  $ids
     * @return list<ExerciseRef>
     */
    public function findForPublicationByIds(array $ids, int $unitId): array;
}
