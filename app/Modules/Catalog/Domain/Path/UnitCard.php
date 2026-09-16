<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Path;

/**
 * Sıralama için gereken asgari ünite bilgisi.
 *
 * Bilinçli olarak ünitenin tamamı değil: PathStrategy'nin başlığa, açıklamaya
 * veya node'lara ihtiyacı yok. Domain'e yalnızca kararı etkileyen veri girer.
 */
final readonly class UnitCard
{
    public function __construct(
        public int $id,
        public int $sortOrder,
        public ?int $gradeLevel = null,
    ) {}
}
