<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCase;

use App\Modules\Catalog\Domain\Unlock\LockReason;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;

final readonly class NodePathView
{
    public function __construct(
        public UnitNode $node,
        public bool $completed,
        public bool $unlocked,
        public ?LockReason $lockReason,
        public int $bestAccuracy,
    ) {}

    /** locked | available | completed */
    public function state(): string
    {
        return match (true) {
            $this->completed => 'completed',
            $this->unlocked => 'available',
            default => 'locked',
        };
    }
}
