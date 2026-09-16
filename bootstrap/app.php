<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Exception\LearningException;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Middleware\OptionalAuthentication;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['auth.optional' => OptionalAuthentication::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Oyun döngüsünün beklenen hataları API hata zarfına çevrilir;
        // istemci koda göre dallanır (can bitti modalı, kilit uyarısı...).
        $exceptions->render(function (LearningException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status, $e->details);
        });
    })->create();
