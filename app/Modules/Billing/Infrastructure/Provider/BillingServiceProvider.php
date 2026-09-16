<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Provider;

use App\Modules\Billing\Domain\Contract\SubscriptionGateway;
use App\Modules\Billing\Infrastructure\Eloquent\Repository\EloquentEntitlementReader;
use App\Modules\Billing\Infrastructure\RevenueCat\RevenueCatGateway;
use App\Shared\Domain\Entitlement\EntitlementReader;
use App\Shared\Http\ModuleRoutes;
use Illuminate\Support\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SubscriptionGateway::class, static fn (): RevenueCatGateway => new RevenueCatGateway(
            webhookSecret: (string) config('tekrarla.billing.revenuecat.webhook_secret'),
            monthlyProductId: (string) config('tekrarla.billing.products.monthly'),
            yearlyProductId: (string) config('tekrarla.billing.products.yearly'),
        ));

        // Son geçici bağlama da kalktı: premium artık gerçekten mümkün.
        $this->app->bind(EntitlementReader::class, EloquentEntitlementReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        ModuleRoutes::api(__DIR__.'/../../Routes/api.php');
    }
}
