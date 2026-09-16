<?php

declare(strict_types=1);

namespace App\Modules\Ads\Infrastructure\AdMob;

use App\Modules\Ads\Domain\Verification\AdMobKeyProvider;
use App\Modules\Ads\Domain\Verification\AdVerificationFailed;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;

/**
 * AdMob'un açık anahtarlarını çeker ve önbelleğe alır.
 *
 * Her ödül doğrulamasında Google'a istek atmak, reklam gelirini onların
 * uptime'ına bağlar. 6 saat, anahtar rotasyonu için fazlasıyla güvenli.
 */
final readonly class CachedAdMobKeyProvider implements AdMobKeyProvider
{
    private const TTL_SECONDS = 21600;

    public function __construct(
        private Http $http,
        private Cache $cache,
        private string $url,
    ) {}

    /** @return array<int, string> */
    public function keys(): array
    {
        return $this->cache->remember('admob:keys', self::TTL_SECONDS, function (): array {
            $response = $this->http->timeout(5)->get($this->url);

            if (! $response->successful()) {
                throw AdVerificationFailed::because('AdMob anahtarlarına ulaşılamadı');
            }

            $keys = [];

            foreach ((array) $response->json('keys', []) as $key) {
                if (isset($key['keyId'], $key['pem'])) {
                    $keys[(int) $key['keyId']] = (string) $key['pem'];
                }
            }

            if ($keys === []) {
                // Boş liste önbelleğe alınmamalı: 6 saat boyunca tüm
                // ödülleri reddetmemize yol açardı.
                throw AdVerificationFailed::because('AdMob boş anahtar listesi döndü');
            }

            return $keys;
        });
    }
}
