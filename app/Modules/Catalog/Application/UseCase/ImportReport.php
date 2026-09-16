<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCase;

final readonly class ImportReport
{
    public function __construct(
        public int $unitId,
        public string $unitTitle,
        public int $topicCount,
        public int $exerciseCount,
        public int $nodeCount,
    ) {}
}
