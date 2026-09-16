<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Infrastructure\Provider;

use App\Modules\Catalog\Domain\Contract\BlueprintReader;
use App\Modules\Curriculum\Domain\Contract\VariantCourseReader;
use App\Modules\Curriculum\Infrastructure\Eloquent\Repository\EloquentBlueprintReader;
use App\Modules\Curriculum\Infrastructure\Eloquent\Repository\EloquentVariantCourseReader;
use App\Shared\Http\ModuleRoutes;
use Illuminate\Support\ServiceProvider;

final class CurriculumServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VariantCourseReader::class, EloquentVariantCourseReader::class);
        // Catalog'un blueprint sözleşmesini Curriculum uygular: deneme
        // kompozisyonu sınav yapısının parçası, içeriğin değil.
        $this->app->bind(BlueprintReader::class, EloquentBlueprintReader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        ModuleRoutes::api(__DIR__.'/../../Routes/api.php');
    }
}
