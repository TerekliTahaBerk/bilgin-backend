<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Social;

use App\Modules\Identity\Domain\Enum\SocialProvider;

/**
 * Doğrulanmış sosyal kimlik.
 *
 * Bu nesne ancak sağlayıcının imzası doğrulandıktan SONRA üretilir;
 * varlığı "bu kullanıcı gerçekten o kişi" anlamına gelir.
 */
final readonly class SocialIdentity
{
    public function __construct(
        public SocialProvider $provider,
        public string $providerUserId,
        public ?string $email = null,
        public bool $emailVerified = false,
        public ?string $name = null,
    ) {}

    /**
     * E-posta hesap eşleştirmede kullanılabilir mi?
     *
     * Apple "e-postamı gizle" seçeneğinde takma adres verir ve kullanıcı onu
     * sonradan kapatabilir. Doğrulanmamış e-postayla hesap eşleştirmek,
     * başkasının hesabını ele geçirmenin yolu olur.
     */
    public function emailIsTrustworthy(): bool
    {
        return $this->email !== null && $this->emailVerified;
    }
}
