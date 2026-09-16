<?php

declare(strict_types=1);

namespace App\Shared\Http\Resource;

use App\Shared\Domain\Hearts\HeartBalance;

final readonly class HeartResource
{
    /** @return array<string, mixed> */
    public static function toArray(HeartBalance $balance): array
    {
        return array_filter([
            'hearts' => $balance->hearts,
            'max' => $balance->max,
            'unlimited' => $balance->unlimited ?: null,
            'next_heart_at' => $balance->nextHeartAt?->format(DATE_ATOM),
            'full_at' => $balance->fullAt?->format(DATE_ATOM),
        ], static fn ($v): bool => $v !== null);
    }
}
