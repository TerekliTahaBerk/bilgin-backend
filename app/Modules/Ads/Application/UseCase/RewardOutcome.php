<?php

declare(strict_types=1);

namespace App\Modules\Ads\Application\UseCase;

/**
 * Ödül sonucu.
 *
 * Üçü de HTTP 200 döner: AdMob'a "aldım, tekrar gönderme" demek istiyoruz.
 * Reddedilme sebepleri kayıtta ve panelde görünür.
 */
final readonly class RewardOutcome
{
    private function __construct(
        public string $outcome,
        public ?string $detail = null,
        public ?int $hearts = null,
        public ?int $remainingToday = null,
    ) {}

    public static function granted(int $hearts, int $remainingToday): self
    {
        return new self('granted', null, $hearts, $remainingToday);
    }

    public static function duplicate(): self
    {
        return new self('duplicate', 'Bu ödül daha önce işlendi.');
    }

    public static function rejected(string $reason): self
    {
        return new self('rejected', $reason);
    }
}
