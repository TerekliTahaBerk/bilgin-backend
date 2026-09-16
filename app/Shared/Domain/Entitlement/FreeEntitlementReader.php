<?php

declare(strict_types=1);

namespace App\Shared\Domain\Entitlement;

/** Billing modülü gelene kadarki varsayılan: herkes ücretsiz plandadır. */
final class FreeEntitlementReader implements EntitlementReader
{
    public function for(int $userId): Entitlements
    {
        return Entitlements::free();
    }
}
