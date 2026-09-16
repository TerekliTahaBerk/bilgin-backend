<?php

declare(strict_types=1);

namespace App\Modules\Hearts\Domain\Policy;

/** Premium: can hiç eksilmez, UI ∞ gösterir. */
final readonly class UnlimitedHeartPolicy implements HeartPolicy
{
    public function consumesHearts(): bool
    {
        return false;
    }

    public function maxHearts(int $configuredMax): int
    {
        return $configuredMax;
    }
}
