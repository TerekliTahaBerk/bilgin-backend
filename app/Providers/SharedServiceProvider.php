<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Clock\ClockInterface;
use App\Shared\Clock\SystemClock;
use App\Shared\Domain\Entitlement\EntitlementReader;
use App\Shared\Domain\Entitlement\FreeEntitlementReader;
use Illuminate\Support\ServiceProvider;

final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ClockInterface::class, SystemClock::class);
        // Billing modülü gelince burası gerçek abonelik okuyucusuyla değişir.
        $this->app->bind(EntitlementReader::class, FreeEntitlementReader::class);
    }
}
