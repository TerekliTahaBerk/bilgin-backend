<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Illuminate\Support\Facades\Route;

/**
 * Modül route dosyalarını tek ve tutarlı bir şekilde yükler.
 *
 * Her modülün kendi provider'ında prefix/middleware tekrarlaması, zamanla
 * birinin unutulmasıyla sonuçlanır — bir modülün uçları /api altında,
 * diğerininki kökte kalır. Tek giriş noktası bunu imkânsız kılar.
 */
final class ModuleRoutes
{
    public static function api(string $path): void
    {
        Route::prefix('api')->middleware('api')->group($path);
    }
}
