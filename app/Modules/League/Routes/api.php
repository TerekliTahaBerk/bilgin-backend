<?php

declare(strict_types=1);

use App\Modules\League\Http\Controller\Api\V1\LeagueController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('league/current', [LeagueController::class, 'current'])->name('league.current');
    Route::get('league/history', [LeagueController::class, 'history'])->name('league.history');
});
