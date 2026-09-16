<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token varsa çözer, yoksa isteği geçirir.
 *
 * Sosyal girişte iki akış da geçerli: misafir token'ıyla gelen kullanıcının
 * ilerlemesi taşınır, token'sız gelen için yeni hesap açılır. `auth:sanctum`
 * ikincisini 401'e düşürür, middleware'siz bırakmak ise birincisini imkânsız
 * kılardı.
 */
final class OptionalAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null) {
            // Geçersiz token sessizce yok sayılır: girişin kendisi zaten
            // sağlayıcı token'ıyla doğrulanıyor.
            auth('sanctum')->user();
        }

        return $next($request);
    }
}
