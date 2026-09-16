<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Unlock;

/** Bir node'un kilidinin değerlendirildiği bağlam. */
final readonly class UnlockContext
{
    /** @param  list<int>  $unitStudyNodeIds  ünitedeki challenge olmayan node'lar */
    public function __construct(
        public int $nodeId,
        public int $unitId,
        public ?int $previousNodeId,
        public ?int $previousUnitId,
        public array $unitStudyNodeIds,
        public ProgressSnapshot $progress,
        public bool $hasPremium,
        public bool $nodeRequiresPremium = false,
    ) {}
}
