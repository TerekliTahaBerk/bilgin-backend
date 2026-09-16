<?php

declare(strict_types=1);

namespace App\Modules\Hearts\Domain;

use DateTimeImmutable;

final readonly class RegenerationResult
{
    public function __construct(
        public int $hearts,
        public DateTimeImmutable $lastRegenAt,
        public int $earned,
    ) {}

    public function changed(): bool
    {
        return $this->earned > 0;
    }
}
