<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controller\Api\V1\AuthController;
use App\Modules\Identity\Http\Controller\Api\V1\MeController;
use App\Modules\Identity\Http\Controller\Api\V1\OnboardingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Kimlik doğrulama gerektirmeyen tek uç: misafir giriş.
    Route::post('auth/guest', [AuthController::class, 'guest'])
        ->middleware('throttle:10,1')
        ->name('auth.guest');

    /*
     | Sosyal giriş kimlik doğrulaması OPSİYONEL bir middleware ile sarılı:
     | misafir token'ıyla çağrılırsa ilerleme taşınır, token'sız çağrılırsa
     | yeni hesap açılır. İkisi de geçerli akış.
     */
    Route::post('auth/social', [AuthController::class, 'social'])
        ->middleware(['throttle:10,1', 'auth.optional'])
        ->name('auth.social');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [MeController::class, 'show'])->name('me.show');
        Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
        Route::patch('me/enrollment', [OnboardingController::class, 'changeField'])->name('me.enrollment.update');
    });
});
