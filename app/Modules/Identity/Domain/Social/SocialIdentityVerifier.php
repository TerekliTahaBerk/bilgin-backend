<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Social;

use App\Modules\Identity\Domain\Enum\SocialProvider;

/**
 * Sağlayıcının kimlik token'ını doğrular.
 *
 * Doğrulama ASLA atlanamaz: istemciden gelen "ben şu Apple kullanıcısıyım"
 * iddiası tek başına imzasız bir metindir. Token'ı imzalayan gerçekten
 * Apple/Google mı, izleyici (audience) bizim uygulamamız mı, süresi dolmuş mu —
 * üçü de kontrol edilir.
 */
interface SocialIdentityVerifier
{
    public function provider(): SocialProvider;

    /** @throws SocialVerificationFailed */
    public function verify(string $identityToken): SocialIdentity;
}
