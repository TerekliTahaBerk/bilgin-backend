<?php

declare(strict_types=1);

use App\Modules\Billing\Infrastructure\Eloquent\Model\Subscription;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

/*
 | Abonelik ve premium kapıları.
 |
 | Buradaki testlerin çoğu para ile ilgili: çift işlenen bir webhook ya
 | ücretsiz kullanıcıya premium verir ya da ödeyenin erişimini keser.
 | İkisi de sessizdir ve ancak kullanıcı şikâyet ettiğinde fark edilir.
 */

beforeEach(function (): void {
    config(['tekrarla.billing.revenuecat.webhook_secret' => 'test-secret']);

    $this->seed(DatabaseSeeder::class);

    $this->token = $this->postJson('/api/v1/auth/guest', [
        'device_identifier' => 'billing-testi',
        'platform' => 'ios',
    ])->json('data.token');

    $this->withToken($this->token)->postJson('/api/v1/onboarding', [
        'exam_code' => 'yks', 'field' => 'say', 'grade' => '11',
    ]);

    $this->user = User::query()->firstOrFail();
});

/** RevenueCat webhook payload'ı üretir. */
function webhookPayload(array $overrides = []): array
{
    return ['event' => array_merge([
        'id' => 'evt_'.uniqid(),
        'type' => 'INITIAL_PURCHASE',
        'app_user_id' => test()->user->uuid,
        'product_id' => 'tekrarla_premium_yearly',
        'store' => 'APP_STORE',
        'transaction_id' => 'txn_123',
        'event_timestamp_ms' => now()->getTimestampMs(),
        'expiration_at_ms' => now()->addYear()->getTimestampMs(),
        'period_type' => 'NORMAL',
    ], $overrides)];
}

function sendWebhook(array $payload, string $secret = 'test-secret')
{
    return test()->postJson('/api/v1/webhooks/revenuecat', $payload, ['Authorization' => $secret]);
}

it('imzasız webhook reddedilir', function (): void {
    sendWebhook(webhookPayload(), 'yanlis-sir')->assertUnauthorized();

    expect(Subscription::query()->count())->toBe(0);
});

it('sır tanımlı değilse webhook tamamen kapalıdır', function (): void {
    // "Doğrulama yapamıyorsam kabul edeyim" davranışı, uca herkesin
    // premium yazabilmesi demek olurdu.
    config(['tekrarla.billing.revenuecat.webhook_secret' => '']);

    sendWebhook(webhookPayload(), '')->assertUnauthorized();
});

it('satın alma aboneliği açar ve premium hakkı verir', function (): void {
    sendWebhook(webhookPayload())->assertOk()->assertJsonPath('data.outcome', 'processed');

    $response = $this->withToken($this->token)->getJson('/api/v1/me/entitlements')->assertOk();

    expect($response->json('data.premium.active'))->toBeTrue()
        ->and($response->json('data.premium.plan'))->toBe('yearly')
        ->and($response->json('data.limits.unlimited_hearts'))->toBeTrue()
        ->and($response->json('data.limits.ads'))->toBeFalse();
});

it('aynı olay iki kez işlenmez', function (): void {
    // Sağlayıcı kendi yeniden deneme politikasıyla aynı olayı tekrar gönderir.
    $payload = webhookPayload();

    sendWebhook($payload)->assertJsonPath('data.outcome', 'processed');
    sendWebhook($payload)->assertJsonPath('data.outcome', 'duplicate');

    expect(DB::table('subscription_events')->count())->toBe(1)
        ->and(Subscription::query()->count())->toBe(1);
});

it('bilinmeyen kullanıcı hata değildir ama kaydedilir', function (): void {
    // 500 dönmek sağlayıcının saatlerce yeniden denemesine yol açar.
    sendWebhook(webhookPayload(['app_user_id' => 'olmayan-kullanici']))
        ->assertOk()
        ->assertJsonPath('data.outcome', 'ignored');

    expect(DB::table('subscription_events')->where('error', 'like', '%bulunamadı%')->exists())->toBeTrue();
});

it('ilgilenmediğimiz olay tipi bile ham olarak saklanır', function (): void {
    sendWebhook(webhookPayload(['type' => 'SUBSCRIBER_ALIAS']))
        ->assertOk()
        ->assertJsonPath('data.outcome', 'ignored');

    expect(DB::table('subscription_events')->count())->toBe(1);
});

it('ödeme hatasında erişim KESİLMEZ', function (): void {
    // Kartı geçmeyen kullanıcının erişimini anında kesmek, çoğu zaman
    // bankadan kaynaklanan bir sorun için müşteriyi cezalandırmaktır.
    sendWebhook(webhookPayload());
    sendWebhook(webhookPayload(['type' => 'BILLING_ISSUE']));

    $response = $this->withToken($this->token)->getJson('/api/v1/me/entitlements');

    expect($response->json('data.premium.active'))->toBeTrue()
        ->and($response->json('data.premium.status'))->toBe('grace');
});

it('iptal sonrası dönem sonuna kadar erişim sürer', function (): void {
    sendWebhook(webhookPayload());
    sendWebhook(webhookPayload([
        'type' => 'CANCELLATION',
        'expiration_at_ms' => now()->addMonth()->getTimestampMs(),
    ]));

    expect($this->withToken($this->token)->getJson('/api/v1/me/entitlements')->json('data.premium.active'))
        ->toBeTrue();
});

it('iade erişimi ANINDA keser', function (): void {
    // Sağlayıcı iade olayında bitiş tarihini geleceği gösterecek şekilde
    // gönderebilir; ona güvenmek parasını geri alanın premium kullanması demek.
    sendWebhook(webhookPayload());
    sendWebhook(webhookPayload([
        'type' => 'REFUND',
        'expiration_at_ms' => now()->addYear()->getTimestampMs(),
    ]));

    expect($this->withToken($this->token)->getJson('/api/v1/me/entitlements')->json('data.premium.active'))
        ->toBeFalse();
});

it('süresi dolan abonelik erişim vermez', function (): void {
    sendWebhook(webhookPayload(['expiration_at_ms' => now()->subDay()->getTimestampMs()]));

    expect($this->withToken($this->token)->getJson('/api/v1/me/entitlements')->json('data.premium.active'))
        ->toBeFalse();
});

it('premium kullanıcının canı eksilmez', function (): void {
    sendWebhook(webhookPayload());

    $course = Course::query()->where('code', 'tyt_tarih')->firstOrFail();
    $node = UnitNode::query()->whereIn('unit_id', $course->units()->pluck('id'))->orderBy('sort_order')->firstOrFail();

    $sessionId = $this->withToken($this->token)
        ->postJson('/api/v1/sessions', ['node_id' => $node->id])
        ->assertCreated()
        ->json('data.session_id');

    $item = StudySession::query()->where('uuid', $sessionId)->firstOrFail()->items()->first();

    $result = $this->withToken($this->token)->postJson("/api/v1/sessions/{$sessionId}/answers", [
        'exercise_id' => $item->exercise_id,
        'answer' => ['option_id' => 'yanlis', 'value' => 'yanlis'],
        'elapsed_ms' => 5000,
    ])->assertOk()->json('data');

    expect($result['is_correct'])->toBeFalse()
        ->and($result['hearts']['unlimited'])->toBeTrue()
        ->and($result['hearts']['hearts'])->toBe(5);
});

it('premium kullanıcıya kilitli ders açılır', function (): void {
    $locked = collect($this->withToken($this->token)->getJson('/api/v1/me/courses')->json('data.sections'))
        ->flatMap(fn (array $s): array => $s['courses'])
        ->firstWhere('code', 'tyt_din');

    expect($locked['locked'])->toBeTrue();

    sendWebhook(webhookPayload());

    $unlocked = collect($this->withToken($this->token)->getJson('/api/v1/me/courses')->json('data.sections'))
        ->flatMap(fn (array $s): array => $s['courses'])
        ->firstWhere('code', 'tyt_din');

    expect($unlocked)->not->toHaveKey('locked');
});

it('hak değişimi önbelleği anında düşürür', function (): void {
    // Aksi hâlde kullanıcı satın aldığı premium'u 5 dakika göremezdi.
    expect($this->withToken($this->token)->getJson('/api/v1/me/entitlements')->json('data.premium.active'))
        ->toBeFalse();

    sendWebhook(webhookPayload());

    expect($this->withToken($this->token)->getJson('/api/v1/me/entitlements')->json('data.premium.active'))
        ->toBeTrue();
});

it('paywall karşılaştırması fiyat içermez', function (): void {
    // Fiyat ülkeye, para birimine ve kampanyaya göre değişir; tek doğru
    // kaynağı store SDK'sıdır.
    $data = $this->withToken($this->token)->getJson('/api/v1/premium/offerings')->assertOk()->json('data');

    expect($data['products'])->toHaveCount(2)
        ->and($data['trial_days'])->toBe(7)
        ->and($data['not_for_sale'])->toContain('Lig sıralaması')
        ->and(json_encode($data))->not->toContain('price');
});
