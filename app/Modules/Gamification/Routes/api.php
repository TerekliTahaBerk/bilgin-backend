<?php

declare(strict_types=1);

use App\Modules\Gamification\Http\Controller\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('me/stats', [ProfileController::class, 'stats'])->name('me.stats');
    Route::get('me/badges', [ProfileController::class, 'badges'])->name('me.badges');
});
