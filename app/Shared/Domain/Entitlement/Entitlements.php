<?php

declare(strict_types=1);

namespace App\Shared\Domain\Entitlement;

/**
 * Kullanıcının o anki hakları.
 *
 * Premium durumu İSTEMCİDEN ALINMAZ. Bu nesne yalnızca sunucu tarafındaki
 * abonelik kaydından üretilir; istemcinin "ben premium'um" demesi hiçbir
 * kapıyı açmaz.
 */
final readonly class Entitlements
{
    public function __construct(
        public bool $premium,
        public ?string $plan = null,
        public int $maxEnrollments = 1,
    ) {}

    public static function free(): self
    {
        return new self(premium: false, maxEnrollments: 1);
    }

    public static function premium(string $plan): self
    {
        return new self(premium: true, plan: $plan, maxEnrollments: PHP_INT_MAX);
    }

    public function canEnrollMore(int $currentCount): bool
    {
        return $currentCount < $this->maxEnrollments;
    }

    public function unlocksPremiumContent(): bool
    {
        return $this->premium;
    }
}
