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
     * Verilen kimliklerden SEÇİLEBİLİR olanları döner.
     *
     * Seçilebilirlik varsayılan olarak "yayında"dır. $publicationUnitId
     * verildiğinde — yalnızca yayın doğrulaması verir — o üniteye ait
     * arşivlenmemiş sorular da seçilebilir sayılır; aksi hâlde elle seçilmiş
     * liste kuralı, henüz yayınlanmamış kendi sorularını göremezdi.
     *
     * @param  list<int>  $ids
     * @return list<ExerciseRef>
     */
    public function findSelectableByIds(array $ids, ?int $publicationUnitId = null): array;
}
