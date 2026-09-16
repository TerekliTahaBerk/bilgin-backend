<?php

declare(strict_types=1);

use App\Modules\Curriculum\Http\Controller\Api\V1\LearnerCourseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('me/courses', [LearnerCourseController::class, 'index'])->name('me.courses');
});
