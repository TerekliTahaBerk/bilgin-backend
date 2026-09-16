<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Social;

/**
 * Sağlayıcının imza anahtarlarını sağlar.
 *
 * Ayrı bir arayüz olmasının sebebi test edilebilirlik: testte kendi ürettiğimiz
 * anahtar çiftiyle token imzalayıp GERÇEK doğrulama kodunu çalıştırabiliyoruz.
 * Doğrulayıcıyı sahtelemek, tam da en kritik kodu test dışı bırakırdı.
 */
interface JwksProvider
{
    /** @return array{keys: list<array<string, mixed>>}  JWKS belgesi */
    public function keys(string $url): array;
}
