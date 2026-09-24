<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Infrastructure\Provider;

use App\Modules\Gamification\Console\ClearDemoDataCommand;
use App\Modules\Gamification\Console\SeedDemoDataCommand;
use App\Modules\Gamification\Domain\Xp\LevelCurve;
use App\Modules\Gamification\Domain\Xp\XpCalculator;
use App\Shared\Http\ModuleRoutes;
use Illuminate\Support\ServiceProvider;

final class GamificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LevelCurve::class, static fn (): LevelCurve => new LevelCurve(
            thresholds: array_values((array) config('tekrarla.xp.level_thresholds', [0])),
            stepAfterTable: (int) config('tekrarla.xp.level_step_after_table', 600),
        ));

        $this->app->singleton(XpCalculator::class, static fn (): XpCalculator => new XpCalculator(
            perfectBonus: (int) config('tekrarla.xp.bonuses.perfect', 20),
            firstCompletionBonus: (int) config('tekrarla.xp.bonuses.first_completion', 60),
            replayFactor: (float) config('tekrarla.xp.replay_factor', 0.5),
            belowThresholdFactor: (float) config('tekrarla.xp.below_threshold_factor', 0.5),
        ));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([SeedDemoDataCommand::class, ClearDemoDataCommand::class]);
        }

        ModuleRoutes::api(__DIR__.'/../../Routes/api.php');
    }
}
