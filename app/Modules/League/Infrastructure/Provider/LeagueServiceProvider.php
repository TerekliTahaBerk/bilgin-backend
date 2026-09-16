<?php

declare(strict_types=1);

namespace App\Modules\League\Infrastructure\Provider;

use App\Modules\League\Application\Listener\RecordLeagueXpListener;
use App\Modules\League\Console\CloseLeagueWeekCommand;
use App\Modules\League\Domain\Promotion\PromotionRule;
use App\Shared\Domain\Event\XpAwarded;
use App\Shared\Http\ModuleRoutes;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class LeagueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PromotionRule::class, static fn (): PromotionRule => new PromotionRule(
            promotionCount: (int) config('tekrarla.league.promotion_count'),
            demotionCount: (int) config('tekrarla.league.demotion_count'),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        ModuleRoutes::api(__DIR__.'/../../Routes/api.php');

        Event::listen(XpAwarded::class, RecordLeagueXpListener::class);

        if ($this->app->runningInConsole()) {
            $this->commands([CloseLeagueWeekCommand::class]);

            $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
                // Pazartesi 00:00 Europe/Istanbul: biten hafta kapanır.
                // withoutOverlapping, uzun süren kapanışın ikinci kez
                // tetiklenmesini önler (job zaten idempotent ama boşuna koşmasın).
                $schedule->command('league:close')
                    ->weeklyOn(1, '00:00')
                    ->timezone(config('tekrarla.league.timezone'))
                    ->withoutOverlapping();
            });
        }
    }
}
