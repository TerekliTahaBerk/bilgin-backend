<?php

declare(strict_types=1);

namespace App\Modules\Hearts\Infrastructure\Provider;

use App\Modules\Hearts\Application\UseCase\HeartService;
use App\Modules\Hearts\Domain\HeartRegeneration;
use App\Shared\Domain\Hearts\HeartBalanceReader;
use App\Shared\Domain\Hearts\HeartConsumer;
use App\Shared\Http\ModuleRoutes;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class HeartsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HeartRegeneration::class, static fn (): HeartRegeneration => new HeartRegeneration(
            max: (int) config('tekrarla.hearts.max', 5),
            intervalMinutes: (int) config('tekrarla.hearts.regen_interval_minutes', 18),
        ));

        $this->app->singleton(HeartService::class);

        // İnce arayüzler (ISP): okuyan taraf can harcayamaz.
        $this->app->bind(HeartBalanceReader::class, static fn (Application $app): HeartService => $app->make(HeartService::class));
        $this->app->bind(HeartConsumer::class, static fn (Application $app): HeartService => $app->make(HeartService::class));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        ModuleRoutes::api(__DIR__.'/../../Routes/api.php');
    }
}
