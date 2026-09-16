<?php

declare(strict_types=1);

namespace App\Modules\Ads\Infrastructure\AdMob;

use App\Modules\Ads\Domain\Enum\AdPlacement;
use App\Modules\Ads\Domain\Verification\AdMobKeyProvider;
use App\Modules\Ads\Domain\Verification\AdReward;
use App\Modules\Ads\Domain\Verification\AdRewardVerifier;
use App\Modules\Ads\Domain\Verification\AdVerificationFailed;

/**
 * AdMob sunucu tarafı doğrulama (SSV).
 *
 * AdMob, ödüllü reklam izlendiğinde bizim callback URL'imize GET atar ve
 * sorgu dizesini ECDSA (SHA-256) ile imzalar. İmza, `&signature=` parametresine
 * KADAR olan ham sorgu dizesi üzerinde hesaplanır — parametrelerin sırası
 * önemlidir, bu yüzden yeniden inşa edilmez, istekten olduğu gibi alınır.
 *
 * Kritik ayrıntı: `signature` ve `key_id` imzaya DAHİL DEĞİLDİR. Bunları da
 * imzalanan metne katmak, hiçbir doğrulamanın geçmemesine yol açar.
 */
final readonly class AdMobSsvVerifier implements AdRewardVerifier
{
    public function __construct(
        private AdMobKeyProvider $keys,
        private int $maxAgeSeconds = 3600,
    ) {}

    /** @param  array<string, mixed>  $query */
    public function verify(array $query, string $rawQuery): AdReward
    {
        foreach (['signature', 'key_id', 'transaction_id', 'user_id'] as $required) {
            if (($this->text($query, $required) ?? '') === '') {
                throw AdVerificationFailed::because("eksik parametre: {$required}");
            }
        }

        $this->assertFresh($query);

        $content = $this->signedContent($rawQuery);
        $signature = $this->decodeSignature($this->text($query, 'signature') ?? '');
        $publicKey = $this->publicKeyFor((int) ($this->text($query, 'key_id') ?? '0'));

        $result = openssl_verify($content, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($result !== 1) {
            throw AdVerificationFailed::because('imza geçersiz');
        }

        return new AdReward(
            transactionId: $this->text($query, 'transaction_id') ?? '',
            providerUserId: $this->text($query, 'user_id') ?? '',
            placement: AdPlacement::fromCustomData($this->text($query, 'custom_data')),
            amount: max(1, (int) ($this->text($query, 'reward_amount') ?? '1')),
            rewardItem: $this->text($query, 'reward_item'),
            adUnit: $this->text($query, 'ad_unit'),
        );
    }

    /**
     * İmzalanan metin: ham sorgu dizesinin `&signature=` öncesi kısmı.
     *
     * Parametreleri yeniden birleştirmek YANLIŞ olur — sıra ve kodlama
     * farkları imzayı bozar ve geçerli ödüller reddedilir.
     */
    private function signedContent(string $rawQuery): string
    {
        $position = strpos($rawQuery, '&signature=');

        if ($position === false) {
            throw AdVerificationFailed::because('imza parametresi sorgu dizesinde bulunamadı');
        }

        return substr($rawQuery, 0, $position);
    }

    private function decodeSignature(string $signature): string
    {
        // AdMob base64url kullanıyor (- ve _), standart base64 değil.
        $normalized = strtr($signature, '-_', '+/');
        $padded = str_pad($normalized, (int) (ceil(strlen($normalized) / 4) * 4), '=');
        $decoded = base64_decode($padded, true);

        if ($decoded === false) {
            throw AdVerificationFailed::because('imza çözümlenemedi');
        }

        return $decoded;
    }

    /**
     * Sorgu parametresini güvenle metne çevirir.
     *
     * Sorgu dizesinde `?a[]=1` gibi dizi de gelebilir; bunları sessizce
     * metne zorlamak yerine yok sayıyoruz — beklenen biçimde değilse
     * doğrulama zaten başarısız olmalı.
     *
     * @param  array<string, mixed>  $query
     */
    private function text(array $query, string $key): ?string
    {
        $value = $query[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    private function publicKeyFor(int $keyId): string
    {
        $keys = $this->keys->keys();

        return $keys[$keyId]
            // Bilinmeyen anahtar kimliği: ya AdMob anahtar döndürdü ve
            // önbelleğimiz eski, ya da istek sahte. İkisinde de reddedilir.
            ?? throw AdVerificationFailed::because("bilinmeyen anahtar kimliği: {$keyId}");
    }

    /**
     * Eski callback'i reddeder.
     *
     * İmza doğru olsa bile bir kez yakalanan callback sonsuza kadar
     * tekrar oynatılabilirdi; transaction_id tekilliği bunu zaten
     * engelliyor ama zaman penceresi ikinci bir savunma hattı.
     *
     * @param  array<string, string>  $query
     */
    /** @param  array<string, mixed>  $query */
    private function assertFresh(array $query): void
    {
        $timestamp = $this->text($query, 'timestamp');

        if ($timestamp === null) {
            return;
        }

        // AdMob milisaniye gönderiyor.
        $age = time() - intdiv((int) $timestamp, 1000);

        if ($age > $this->maxAgeSeconds) {
            throw AdVerificationFailed::because('callback çok eski');
        }
    }
}
