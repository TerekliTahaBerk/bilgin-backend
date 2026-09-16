<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Contract;

use App\Modules\Billing\Application\DTO\SubscriptionEvent;

/**
 * Abonelik sağlayıcısı soyutlaması.
 *
 * RevenueCat'ten Adapty'ye ya da doğrudan store doğrulamasına geçmek, bu
 * arayüzün ikinci bir implementasyonu demek; iş kuralları (grace, refund,
 * entitlement) sağlayıcıdan bağımsız kalır.
 */
interface SubscriptionGateway
{
    /** İsteğin gerçekten sağlayıcıdan geldiğini doğrular. */
    public function verifySignature(string $authorizationHeader): bool;

    /**
     * Sağlayıcının payload'ını iç modele çevirir.
     *
     * @param  array<string, mixed>  $payload
     */
    public function parse(array $payload): ?SubscriptionEvent;
}
