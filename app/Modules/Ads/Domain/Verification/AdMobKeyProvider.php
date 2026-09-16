<?php

declare(strict_types=1);

namespace App\Modules\Ads\Domain\Verification;

/**
 * AdMob'un imza doğrulama anahtarları.
 *
 * Ayrı arayüz: testte kendi ürettiğimiz EC anahtar çiftiyle imzalayıp
 * GERÇEK openssl doğrulamasını koşturabiliyoruz. Doğrulayıcıyı sahtelemek,
 * reklam izlemeden can kazanmayı mümkün kılan kodu test dışı bırakırdı.
 */
interface AdMobKeyProvider
{
    /** @return array<int, string>  key_id → PEM formatında açık anahtar */
    public function keys(): array;
}
