<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Contract;

use App\Modules\Catalog\Domain\Blueprint\BlueprintSpec;

/**
 * Deneme kompozisyonunu okur. Implementasyonu Curriculum'de —
 * Catalog o modülü tanımaz, yalnızca bu arayüzü.
 */
interface BlueprintReader
{
    public function find(int $blueprintId): ?BlueprintSpec;

    /** @return list<BlueprintSpec> */
    public function forExamVariant(int $examVariantId): array;
}
