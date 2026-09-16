<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\DTO;

final readonly class StartSessionCommand
{
    public function __construct(
        public int $userId,
        public int $nodeId,
        public ?string $idempotencyKey = null,
    ) {}
}
