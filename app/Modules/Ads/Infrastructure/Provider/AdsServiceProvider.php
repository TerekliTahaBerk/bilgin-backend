<?php

declare(strict_types=1);

namespace App\Modules\Ads\Infrastructure\Provider;

use App\Modules\Ads\Domain\Verification\AdMobKeyProvider;
use App\Modules\Ads\Domain\Verification\AdRewardVerifier;
use App\Modules\Ads\Infrastructure\AdMob\AdMobSsvVerifier;
use App\Modules\Ads\Infrastructure\AdMob\CachedAdMobKeyProvider;
use App\Shared\Http\ModuleRoutes;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

final class AdsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdMobKeyProvider::class, static fn (Application $app): CachedAdMobKeyProvider => new CachedAdMobKeyProvider(
            $app->make(Factory::class),
            $app->make(Repository::class),
            (string) config('tekrarla.ads.admob.keys_url'),
        ));

        $this->app->bind(AdRewardVerifier::class, static fn (Application $app): AdMobSsvVerifier => new AdMobSsvVerifier(
            $app->make(AdMobKeyProvider::class),
            (int) config('tekrarla.ads.admob.max_callback_age_seconds'),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        ModuleRoutes::api(__DIR__.'/../../Routes/api.php');
    }
}
