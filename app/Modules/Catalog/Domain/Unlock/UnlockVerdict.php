<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Unlock;

final readonly class UnlockVerdict
{
    private function __construct(
        public bool $satisfied,
        public ?LockReason $reason = null,
    ) {}

    public static function unlocked(): self
    {
        return new self(true);
    }

    public static function locked(LockReason $reason): self
    {
        return new self(false, $reason);
    }
}
