<?php

declare(strict_types=1);

use App\Modules\Catalog\Http\Controller\Api\V1\CoursePathController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('courses/{course}/path', [CoursePathController::class, 'show'])->name('courses.path');
});
