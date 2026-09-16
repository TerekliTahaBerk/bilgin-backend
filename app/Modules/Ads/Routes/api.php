<?php

declare(strict_types=1);

use App\Modules\Ads\Http\Controller\Api\V1\AdController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // SSV callback'ini AdMob çağırır, kullanıcı değil: token yok, imza var.
    Route::get('webhooks/admob/ssv', [AdController::class, 'ssv'])->name('webhooks.admob');

    Route::get('ads/policy', [AdController::class, 'policy'])
        ->middleware('auth:sanctum')
        ->name('ads.policy');
});
