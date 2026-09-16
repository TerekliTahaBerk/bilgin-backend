<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Social;

use App\Modules\Identity\Domain\Enum\SocialProvider;
use App\Modules\Identity\Domain\Social\JwksProvider;
use App\Modules\Identity\Domain\Social\SocialIdentity;
use App\Modules\Identity\Domain\Social\SocialIdentityVerifier;
use App\Modules\Identity\Domain\Social\SocialVerificationFailed;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Throwable;

/**
 * Apple ve Google'ın ortak doğrulama yolu: ikisi de RS256 imzalı, JWKS ile
 * doğrulanan OpenID Connect kimlik token'ı üretiyor. Fark yalnızca JWKS
 * adresi, beklenen `iss` ve izin verilen `aud` listesinde.
 *
 * Kontrol edilen üç şey ve neden:
 *   - imza  → token'ı gerçekten sağlayıcı mı üretti
 *   - aud   → BİZİM uygulamamız için mi üretildi (başka uygulamanın token'ı
 *             kabul edilirse o uygulamanın kullanıcıları bizim hesaplara girer)
 *   - exp   → süresi dolmuş token yeniden kullanılamasın
 *
 * JWT kütüphanesi imza ve exp'i kendisi doğruluyor; aud ve iss bizde.
 */
final readonly class JwtIdentityVerifier implements SocialIdentityVerifier
{
    /** @param  list<string>  $allowedAudiences */
    public function __construct(
        private SocialProvider $provider,
        private JwksProvider $jwks,
        private string $jwksUrl,
        private string $expectedIssuer,
        private array $allowedAudiences,
    ) {}

    public function provider(): SocialProvider
    {
        return $this->provider;
    }

    public function verify(string $identityToken): SocialIdentity
    {
        if ($this->allowedAudiences === []) {
            throw SocialVerificationFailed::notConfigured($this->provider->value);
        }

        try {
            $keys = JWK::parseKeySet($this->jwks->keys($this->jwksUrl));
            $claims = (array) JWT::decode($identityToken, $keys);
        } catch (SocialVerificationFailed $e) {
            throw $e;
        } catch (Throwable $e) {
            throw SocialVerificationFailed::invalidToken($e->getMessage());
        }

        $this->assertIssuer($claims);
        $this->assertAudience($claims);

        $subject = $claims['sub'] ?? null;

        if (! is_string($subject) || $subject === '') {
            throw SocialVerificationFailed::invalidToken('kullanıcı kimliği (sub) yok');
        }

        return new SocialIdentity(
            provider: $this->provider,
            providerUserId: $subject,
            email: isset($claims['email']) && is_string($claims['email']) ? $claims['email'] : null,
            emailVerified: $this->emailVerified($claims),
            name: isset($claims['name']) && is_string($claims['name']) ? $claims['name'] : null,
        );
    }

    /** @param  array<string, mixed>  $claims */
    private function assertIssuer(array $claims): void
    {
        $issuer = (string) ($claims['iss'] ?? '');

        // Google iki farklı iss kullanıyor (https'li ve https'siz).
        $normalized = str_replace('https://', '', $issuer);

        if ($normalized !== str_replace('https://', '', $this->expectedIssuer)) {
            throw SocialVerificationFailed::invalidToken("beklenmeyen kaynak: {$issuer}");
        }
    }

    /** @param  array<string, mixed>  $claims */
    private function assertAudience(array $claims): void
    {
        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];

        foreach ($audiences as $candidate) {
            if (is_string($candidate) && in_array($candidate, $this->allowedAudiences, true)) {
                return;
            }
        }

        throw SocialVerificationFailed::wrongAudience();
    }

    /**
     * Apple bu alanı string ("true") olarak gönderiyor, Google boolean.
     * Gevşek karşılaştırma yapmak, doğrulanmamış e-postayı doğrulanmış
     * saymaya yol açabilirdi.
     *
     * @param  array<string, mixed>  $claims
     */
    private function emailVerified(array $claims): bool
    {
        $value = $claims['email_verified'] ?? false;

        return $value === true || $value === 'true';
    }
}
