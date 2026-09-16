<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Social;

use App\Modules\Identity\Domain\Social\JwksProvider;
use App\Modules\Identity\Domain\Social\SocialVerificationFailed;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;

/**
 * Apple/Google JWKS uç noktalarından anahtarları çeker ve önbelleğe alır.
 *
 * Önbellek şart: her girişte Apple'a HTTP isteği atmak hem yavaş hem de
 * sağlayıcı kesintisinde tüm girişleri durdurur. 6 saat, anahtar rotasyonu
 * için fazlasıyla güvenli (sağlayıcılar eski anahtarı uzun süre yayında tutar).
 */
final readonly class CachedJwksProvider implements JwksProvider
{
    private const TTL_SECONDS = 21600;

    public function __construct(
        private Http $http,
        private Cache $cache,
    ) {}

    /** @return array{keys: list<array<string, mixed>>} */
    public function keys(string $url): array
    {
        return $this->cache->remember(
            'jwks:'.md5($url),
            self::TTL_SECONDS,
            function () use ($url): array {
                $response = $this->http->timeout(5)->get($url);

                if (! $response->successful()) {
                    throw SocialVerificationFailed::invalidToken('sağlayıcının anahtarlarına ulaşılamadı');
                }

                $document = $response->json();

                // Beklenmedik gövde önbelleğe ALINMAMALI: bozuk bir yanıt
                // 6 saat boyunca tüm girişleri kilitlerdi.
                if (! is_array($document) || ! isset($document['keys']) || ! is_array($document['keys'])) {
                    throw SocialVerificationFailed::invalidToken('sağlayıcı beklenmeyen bir anahtar listesi döndü');
                }

                return ['keys' => array_values($document['keys'])];
            },
        );
    }
}
