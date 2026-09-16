<?php

declare(strict_types=1);

use App\Modules\Billing\Http\Controller\Api\V1\EntitlementController;
use App\Modules\Billing\Http\Controller\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Webhook auth:sanctum ARKASINDA DEĞİL: çağıran sağlayıcı, kullanıcı değil.
    // Doğrulama imza ile yapılır.
    Route::post('webhooks/revenuecat', [WebhookController::class, 'revenuecat'])
        ->name('webhooks.revenuecat');

    Route::get('premium/offerings', [EntitlementController::class, 'offerings'])
        ->middleware('auth:sanctum')
        ->name('premium.offerings');

    Route::get('me/entitlements', [EntitlementController::class, 'show'])
        ->middleware('auth:sanctum')
        ->name('me.entitlements');
});
