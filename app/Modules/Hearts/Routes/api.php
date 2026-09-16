<?php

declare(strict_types=1);

use App\Modules\Hearts\Http\Controller\Api\V1\HeartController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('hearts', [HeartController::class, 'show'])->name('hearts.show');
});
