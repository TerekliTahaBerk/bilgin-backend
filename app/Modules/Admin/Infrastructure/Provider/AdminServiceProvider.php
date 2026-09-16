<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Provider;

use App\Modules\Admin\Http\Middleware\EnsureAdminRole;
use App\Shared\Http\ModuleRoutes;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class AdminServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../Database/Migrations');

        $router->aliasMiddleware('admin.can', EnsureAdminRole::class);

        ModuleRoutes::api(__DIR__.'/../../Routes/api.php');
    }
}
