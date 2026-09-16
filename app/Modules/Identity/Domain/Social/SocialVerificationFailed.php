<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Social;

use RuntimeException;

final class SocialVerificationFailed extends RuntimeException
{
    public static function invalidToken(string $reason): self
    {
        return new self("Kimlik doğrulanamadı: {$reason}");
    }

    public static function wrongAudience(): self
    {
        // Başka bir uygulama için üretilmiş token'ın kabul edilmesi, o
        // uygulamanın kullanıcılarının bizim hesaplarımıza girmesi demek.
        return new self('Bu token başka bir uygulama için üretilmiş.');
    }

    public static function notConfigured(string $provider): self
    {
        return new self("{$provider} girişi yapılandırılmamış.");
    }
}
