<?php

declare(strict_types=1);

namespace App\Modules\Hearts\Domain\Policy;

final readonly class FreeHeartPolicy implements HeartPolicy
{
    public function consumesHearts(): bool
    {
        return true;
    }

    public function maxHearts(int $configuredMax): int
    {
        return $configuredMax;
    }
}
