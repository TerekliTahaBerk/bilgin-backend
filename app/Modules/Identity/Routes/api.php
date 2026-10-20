<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controller\Api\V1\AuthController;
use App\Modules\Identity\Http\Controller\Api\V1\DeviceController;
use App\Modules\Identity\Http\Controller\Api\V1\EnrollmentController;
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
    /*
     | E-posta ile kayıt ve giriş.
     |
     | `auth.optional`: misafir token'ı varsa okunuyor ama ZORUNLU değil.
     | Kayıt hem sıfırdan hem misafirden yükseltme olarak çalışmalı.
     |
     | Giriş ve şifre sıfırlama daha DAR sınırlı (5/dk): ikisi de deneme
     | yanılmaya açık uçlar. Kayıt biraz daha gevşek, çünkü gerçek kullanıcı
     | doğrulama hatası alıp tekrar deneyebiliyor.
     */
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware(['throttle:10,1', 'auth.optional'])
        ->name('auth.register');

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    Route::post('auth/password/forgot', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,1')
        ->name('auth.password.forgot');

    Route::post('auth/password/reset', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:5,1')
        ->name('auth.password.reset');

    /*
     | E-posta doğrulama bağlantısı. İmza doğrulaması `signed` middleware'inde;
     | bağlantı değiştirilirse ya da süresi dolarsa buraya hiç girilmiyor.
     |
     | GET olması bilinçli: kullanıcı bunu e-posta istemcisinden tıklıyor.
     */
    Route::get('auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('auth/social', [AuthController::class, 'social'])
        ->middleware(['throttle:10,1', 'auth.optional'])
        ->name('auth.social');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [MeController::class, 'show'])->name('me.show');
        Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
        Route::patch('me/enrollment', [OnboardingController::class, 'changeField'])->name('me.enrollment.update');

        Route::patch('me/settings', [MeController::class, 'updateSettings'])->name('me.settings.update');

        /*
         | Hesap silme GERİ ALINAMAZ ve dar sınırlı: bu uca dakikada
         | birden fazla gitmek yanlışlıkla ya da kötü niyetle olur.
         */
        Route::delete('me', [MeController::class, 'destroy'])
            ->middleware('throttle:3,1')
            ->name('me.destroy');

        // Birden fazla sınava hazırlanan öğrenci: kayıtlar ve aralarında geçiş.
        Route::get('me/enrollments', [EnrollmentController::class, 'index'])->name('me.enrollments.index');
        Route::post('me/enrollments', [EnrollmentController::class, 'store'])->name('me.enrollments.store');
        Route::patch('me/enrollments/{enrollment}/primary', [EnrollmentController::class, 'makePrimary'])
            ->whereNumber('enrollment')
            ->name('me.enrollments.primary');

        // Bildirim jetonu. Uygulama her açılışta çağırıyor (jeton döner).
        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
    });
});
