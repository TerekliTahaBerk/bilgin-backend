<?php

declare(strict_types=1);

namespace App\Shared\Domain\Entitlement;

/**
 * Billing modülü gelene kadar FreeEntitlementReader'a bağlıdır.
 *
 * Arayüzü şimdiden koymanın sebebi: premium kontrolünü sonradan eklemek,
 * her çağrı yerine "if premium" serpiştirmek demek olurdu. Şimdi koyunca
 * Billing geldiğinde değişen tek şey ServiceProvider'daki bir satır.
 */
interface EntitlementReader
{
    public function for(int $userId): Entitlements;
}
