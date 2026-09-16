<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\RevenueCat;

use App\Modules\Billing\Application\DTO\SubscriptionEvent;
use App\Modules\Billing\Domain\Contract\SubscriptionGateway;
use App\Modules\Billing\Domain\Enum\SubscriptionPlan;
use App\Modules\Billing\Domain\Enum\SubscriptionStatus;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Log;

/**
 * RevenueCat webhook adaptörü.
 *
 * Neden RevenueCat: App Store ve Play Store'un makbuz doğrulama, yenileme,
 * iade ve platformlar arası senkron karmaşasını kendimiz yazmak, ürünle
 * ilgisi olmayan aylarca sürecek bir iş. Bu adaptör o karmaşayı tek dosyada
 * tutar.
 */
final readonly class RevenueCatGateway implements SubscriptionGateway
{
    public function __construct(
        private string $webhookSecret,
        private string $monthlyProductId,
        private string $yearlyProductId,
    ) {}

    public function verifySignature(string $authorizationHeader): bool
    {
        if ($this->webhookSecret === '') {
            // Sır tanımlı değilse webhook KAPALIDIR. "Doğrulama yapamıyorsam
            // kabul edeyim" davranışı, uca herkesin premium yazabilmesi demek.
            return false;
        }

        return hash_equals($this->webhookSecret, $authorizationHeader);
    }

    public function parse(array $payload): ?SubscriptionEvent
    {
        $event = $payload['event'] ?? null;

        if (! is_array($event) || ! isset($event['id'], $event['type'], $event['app_user_id'])) {
            return null;
        }

        $type = (string) $event['type'];
        $status = $this->statusFor($type);

        if ($status === null) {
            // Bilmediğimiz olay tipi (TRANSFER, SUBSCRIBER_ALIAS...) yoksayılır
            // ama ham kaydı saklanır: gerektiğinde geriye dönük işlenebilir.
            return null;
        }

        $productId = (string) ($event['product_id'] ?? '');

        return new SubscriptionEvent(
            providerEventId: (string) $event['id'],
            type: $type,
            appUserId: (string) $event['app_user_id'],
            productId: $productId,
            plan: $this->planFor($productId),
            status: $status,
            store: $this->storeFor((string) ($event['store'] ?? '')),
            providerSubscriptionId: isset($event['transaction_id']) ? (string) $event['transaction_id'] : null,
            occurredAt: $this->toDate($event['event_timestamp_ms'] ?? null) ?? new DateTimeImmutable('now', new DateTimeZone('UTC')),
            expiresAt: $this->toDate($event['expiration_at_ms'] ?? null),
            trialEnd: $type === 'INITIAL_PURCHASE' && ($event['period_type'] ?? '') === 'TRIAL'
                ? $this->toDate($event['expiration_at_ms'] ?? null)
                : null,
        );
    }

    private function statusFor(string $type): ?SubscriptionStatus
    {
        return match ($type) {
            'INITIAL_PURCHASE', 'RENEWAL', 'UNCANCELLATION', 'PRODUCT_CHANGE' => SubscriptionStatus::Active,
            'BILLING_ISSUE' => SubscriptionStatus::Grace,
            'CANCELLATION' => SubscriptionStatus::Cancelled,
            'EXPIRATION' => SubscriptionStatus::Expired,
            'REFUND' => SubscriptionStatus::Refunded,
            default => null,
        };
    }

    /**
     * Ürün kimliğinden plan çıkarır.
     *
     * Tanınmayan ürün kimliği aylık sayılır: yeni bir ürün eklendiğinde
     * kullanıcı premium'suz kalmaktansa daha kısa bir plana düşsün. Durum
     * log'a yazılır ki yapılandırma eksiği fark edilsin.
     */
    private function planFor(string $productId): SubscriptionPlan
    {
        if ($productId === $this->yearlyProductId) {
            return SubscriptionPlan::Yearly;
        }

        if ($productId !== $this->monthlyProductId) {
            Log::warning('Tanınmayan abonelik ürünü', ['product_id' => $productId]);
        }

        return SubscriptionPlan::Monthly;
    }

    private function storeFor(string $store): ?string
    {
        return match (strtoupper($store)) {
            'APP_STORE' => 'app_store',
            'PLAY_STORE' => 'play_store',
            default => null,
        };
    }

    private function toDate(mixed $ms): ?DateTimeImmutable
    {
        if (! is_numeric($ms)) {
            return null;
        }

        return (new DateTimeImmutable('@'.intdiv((int) $ms, 1000)))->setTimezone(new DateTimeZone('UTC'));
    }
}
