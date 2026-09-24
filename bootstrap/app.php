<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Exception\EmailAuthException;
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

        /*
         | Ters vekil (reverse proxy) arkasında çalışıyoruz.
         |
         | Bu ayar olmadan $request->ip() vekilin IP'sini döner: rate limit
         | herkesi tek kullanıcı sayar (bir kişi tüm giriş denemelerini
         | tüketir) ve denetim kaydındaki IP anlamsız olur. Ayrıca üretilen
         | URL'ler http kalır — sağlayıcılara verdiğimiz webhook adresleri
         | dahil.
         |
         | TRUSTED_PROXIES boşsa hiçbir vekil güvenilmez; "*" yalnızca
         | uygulamaya YALNIZCA vekil üzerinden erişilebiliyorsa doğrudur
         | (Coolify/Traefik kurulumu böyledir).
         */
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES') === '*' ? '*' : array_values(array_filter(
                explode(',', (string) env('TRUSTED_PROXIES', ''))
            )),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Oyun döngüsünün beklenen hataları API hata zarfına çevrilir;
        // istemci koda göre dallanır (can bitti modalı, kilit uyarısı...).
        $exceptions->render(function (LearningException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status, $e->details);
        });

        // E-posta kimliğinin beklenen hataları. Ayrı kodlar olması şart:
        // istemci "e-posta zaten var" ile "ilerlemen kaybolacak" durumlarında
        // bambaşka ekranlar göstermeli.
        $exceptions->render(function (EmailAuthException $e) {
            return ApiResponse::error($e->errorCode, $e->getMessage(), $e->status, $e->details);
        });
    })->create();
