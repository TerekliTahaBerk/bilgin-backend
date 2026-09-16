<?php

declare(strict_types=1);

namespace App\Modules\Ads\Domain\Verification;

use RuntimeException;

final class AdVerificationFailed extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self("Reklam ödülü doğrulanamadı: {$reason}");
    }
}
