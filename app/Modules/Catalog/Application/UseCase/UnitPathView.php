<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCase;

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Unit;

final readonly class UnitPathView
{
    /** @param  list<NodePathView>  $nodes */
    public function __construct(
        public Unit $unit,
        public array $nodes,
        public int $completedNodes,
        public int $totalNodes,
    ) {}

    public function completionPercent(): int
    {
        return $this->totalNodes === 0
            ? 0
            : (int) round($this->completedNodes / $this->totalNodes * 100);
    }

    public function isCompleted(): bool
    {
        return $this->totalNodes > 0 && $this->completedNodes === $this->totalNodes;
    }

    /** locked | available | in_progress | completed */
    public function state(): string
    {
        return match (true) {
            $this->isCompleted() => 'completed',
            $this->completedNodes > 0 => 'in_progress',
            $this->hasUnlockedNode() => 'available',
            default => 'locked',
        };
    }

    /** Ünitenin ilk node'u kilitliyse ünite de kilitlidir. */
    public function firstLockReason(): ?string
    {
        foreach ($this->nodes as $node) {
            if ($node->unlocked) {
                return null;
            }

            return $node->lockReason?->value;
        }

        return null;
    }

    private function hasUnlockedNode(): bool
    {
        foreach ($this->nodes as $node) {
            if ($node->unlocked) {
                return true;
            }
        }

        return false;
    }
}
