<?php

declare(strict_types=1);

namespace App\Shared\Domain\Enum;

enum AccessLevel: string
{
    case Free = 'free';
    case Premium = 'premium';

    public function requiresEntitlement(): bool
    {
        return $this === self::Premium;
    }
}
