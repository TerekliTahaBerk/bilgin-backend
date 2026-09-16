<?php

declare(strict_types=1);

use App\Modules\Ads\Domain\Verification\AdMobKeyProvider;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/*
 | Ödüllü reklam — sunucu tarafı doğrulama (SSV).
 |
 | Testler GERÇEK imza doğrulamasını koşuyor: kendi EC anahtar çiftimizi
 | üretip sorgu dizesini imzalıyor, açık anahtarı key provider üzerinden
 | veriyoruz. Doğrulayıcıyı sahtelemek, reklam izlemeden can kazanmayı
 | mümkün kılan kodu test dışı bırakırdı.
 */

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);

    $this->keyPair = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);

    $pem = openssl_pkey_get_details($this->keyPair)['key'];

    $this->app->instance(AdMobKeyProvider::class, new class($pem) implements AdMobKeyProvider
    {
        public function __construct(private string $pem) {}

        public function keys(): array
        {
            return [1234 => $this->pem];
        }
    });

    $this->token = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'reklam-testi', 'platform' => 'ios',
    ])->json('data.token');

    $this->user = User::query()->firstOrFail();

    // Bir yanlış cevapla can eksilt ki ödülün etkisi görünsün.
    DB::table('user_hearts')->insert([
        'user_id' => $this->user->id, 'hearts' => 3,
        'last_regen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
});

/** AdMob'un gönderdiği biçimde imzalı callback URL'i üretir. */
function ssvUrl(array $overrides = [], ?int $keyId = 1234): string
{
    $params = array_merge([
        'ad_network' => '5450213213286189855',
        'ad_unit' => '1234567890',
        'custom_data' => 'heart_refill',
        'reward_amount' => '1',
        'reward_item' => 'can',
        'timestamp' => (string) (time() * 1000),
        'transaction_id' => 'txn-'.bin2hex(random_bytes(8)),
        'user_id' => test()->user->uuid,
    ], $overrides);

    // İmza, sorgu dizesinin &signature= ÖNCESİ kısmı üzerinde hesaplanır.
    $content = http_build_query($params);

    openssl_sign($content, $derSignature, test()->keyPair, OPENSSL_ALGO_SHA256);

    $signature = rtrim(strtr(base64_encode($derSignature), '+/', '-_'), '=');

    return "/api/v1/webhooks/admob/ssv?{$content}&signature={$signature}&key_id={$keyId}";
}

it('geçerli imzayla can verir', function (): void {
    $response = $this->getJson(ssvUrl())->assertOk();

    expect($response->json('data.outcome'))->toBe('granted')
        ->and($response->json('data.hearts'))->toBe(4)
        ->and($response->json('data.remaining_today'))->toBe(3);
});

it('imzası bozuk callback\'i reddeder', function (): void {
    // İmza doğrulanmasa reklam izlemeden can kazanmak bir HTTP isteği kadar kolay.
    $url = ssvUrl();
    $tampered = preg_replace('/&signature=[^&]+/', '&signature=sahte-imza', $url);

    $this->getJson($tampered)
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'AD_VERIFICATION_FAILED');

    expect(DB::table('user_hearts')->value('hearts'))->toBe(3);
});

it('parametresi değiştirilmiş callback\'i reddeder', function (): void {
    // İmza tüm sorgu dizesini kapsıyor; ödül miktarını artırmak imzayı bozar.
    $url = ssvUrl();
    $tampered = str_replace('reward_amount=1', 'reward_amount=99', $url);

    $this->getJson($tampered)->assertUnauthorized();
});

it('bilinmeyen anahtar kimliğini reddeder', function (): void {
    $this->getJson(ssvUrl(keyId: 9999))->assertUnauthorized();
});

it('çok eski callback\'i reddeder', function (): void {
    $old = (string) ((time() - 7200) * 1000);

    $this->getJson(ssvUrl(['timestamp' => $old]))
        ->assertUnauthorized();
});

it('aynı işlem iki kez ödül vermez', function (): void {
    // AdMob ağ hatasında aynı callback'i tekrar gönderir.
    $url = ssvUrl();

    $this->getJson($url)->assertOk()->assertJsonPath('data.outcome', 'granted');
    $this->getJson($url)->assertOk()->assertJsonPath('data.outcome', 'duplicate');

    expect(DB::table('user_hearts')->value('hearts'))->toBe(4)
        ->and(DB::table('ad_rewards')->count())->toBe(1);
});

it('günlük limite ulaşınca ödül vermez', function (): void {
    // Reklamla can sınırsız olsaydı premium'un değeri sıfırlanırdı.
    for ($i = 0; $i < 4; $i++) {
        $this->getJson(ssvUrl())->assertOk();
    }

    $response = $this->getJson(ssvUrl())->assertOk();

    expect($response->json('data.outcome'))->toBe('rejected')
        ->and($response->json('data.detail'))->toContain('limit');

    expect(DB::table('ad_rewards')->where('rejection_reason', 'daily_limit')->count())->toBe(1);
});

it('bilinmeyen kullanıcı için callback\'i yine de kaydeder', function (): void {
    // "Reklamı izledim ama can gelmedi" şikâyetinin izi kalmalı.
    $response = $this->getJson(ssvUrl(['user_id' => '00000000-0000-4000-8000-000000000000']))
        ->assertOk();

    expect($response->json('data.outcome'))->toBe('rejected')
        ->and(DB::table('ad_rewards')->where('rejection_reason', 'user_not_found')->count())->toBe(1);
});

it('reklam politikası ücretsiz kullanıcıya reklam söyler', function (): void {
    $response = $this->withToken($this->token)->getJson('/api/v1/ads/policy')->assertOk();

    expect($response->json('data.show_ads'))->toBeTrue()
        ->and($response->json('data.rewarded.enabled'))->toBeTrue()
        ->and($response->json('data.rewarded.remaining_today'))->toBe(4)
        // Yeni hesapta geçiş reklamı kapalı: ilk günlerde reklamla karşılamak
        // retention'ı en hızlı düşüren şeylerden biri.
        ->and($response->json('data.interstitial.enabled'))->toBeFalse()
        ->and($response->json('data.banner.enabled'))->toBeFalse();
});

it('reklam politikası premium kullanıcıya hiç reklam göstermez', function (): void {
    config(['tekrarla.billing.revenuecat.webhook_secret' => 'test-secret']);

    $this->postJson('/api/v1/webhooks/revenuecat', ['event' => [
        'id' => 'evt-ads', 'type' => 'INITIAL_PURCHASE',
        'app_user_id' => $this->user->uuid,
        'product_id' => 'tekrarla_premium_yearly',
        'event_timestamp_ms' => now()->getTimestampMs(),
        'expiration_at_ms' => now()->addYear()->getTimestampMs(),
    ]], ['Authorization' => 'test-secret'])->assertOk();

    $response = $this->withToken($this->token)->getJson('/api/v1/ads/policy')->assertOk();

    expect($response->json('data.show_ads'))->toBeFalse()
        ->and($response->json('data.rewarded.enabled'))->toBeFalse()
        ->and($response->json('data.interstitial.enabled'))->toBeFalse();
});

it('premium kullanıcıya reklam ödülü verilmez', function (): void {
    config(['tekrarla.billing.revenuecat.webhook_secret' => 'test-secret']);

    $this->postJson('/api/v1/webhooks/revenuecat', ['event' => [
        'id' => 'evt-ads-2', 'type' => 'INITIAL_PURCHASE',
        'app_user_id' => $this->user->uuid,
        'product_id' => 'tekrarla_premium_monthly',
        'event_timestamp_ms' => now()->getTimestampMs(),
        'expiration_at_ms' => now()->addMonth()->getTimestampMs(),
    ]], ['Authorization' => 'test-secret']);

    $response = $this->getJson(ssvUrl())->assertOk();

    expect($response->json('data.outcome'))->toBe('rejected')
        ->and($response->json('data.detail'))->toContain('sınırsız');
});
