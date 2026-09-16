<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Contract;

interface UnitTopicReader
{
    /** @return list<int> */
    public function topicIdsForUnit(int $unitId): array;
}
