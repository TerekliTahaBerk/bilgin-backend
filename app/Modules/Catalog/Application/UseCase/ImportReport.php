<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCase;

final readonly class ImportReport
{
    public function __construct(
        public int $unitId,
        public string $unitTitle,
        public int $topicCount,
        /** Pakette işlenen toplam soru. */
        public int $exerciseCount,
        /**
         * Bu çağrıda YENİ eklenen soru.
         *
         * Toplamdan ayrı: var olan bir paketi tekrar yüklemek 40 soru
         * işler ama 0 tanesini ekler. Tek sayı göstermek ya "hiçbir şey
         * olmadı" ya da "hepsi yeniden yazıldı" izlenimi verirdi; ikisi de
         * yanlış.
         */
        public int $createdCount,
        public int $nodeCount,
    ) {}
}
