<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\UseCase;

/**
 * Webhook sonucu.
 *
 * Üç durumun da HTTP 200 dönmesi bilinçli: sağlayıcıya "aldım, tekrar
 * gönderme" demek istiyoruz. Hata durumları log'da ve panelde görünür.
 */
final readonly class ProcessResult
{
    private function __construct(
        public string $outcome,
        public ?string $detail = null,
    ) {}

    public static function processed(string $status): self
    {
        return new self('processed', $status);
    }

    public static function duplicate(): self
    {
        return new self('duplicate', 'Bu olay daha önce işlendi.');
    }

    public static function ignored(string $reason): self
    {
        return new self('ignored', $reason);
    }
}
