<?php

declare(strict_types=1);

use App\Modules\Admin\Http\Controller\Api\V1\AdminAuthController;
use App\Modules\Admin\Http\Controller\Api\V1\AdminUserController;
use App\Modules\Admin\Http\Controller\Api\V1\ContentController;
use App\Modules\Admin\Http\Controller\Api\V1\CurriculumMapController;
use App\Modules\Admin\Http\Controller\Api\V1\ExerciseController;
use Illuminate\Support\Facades\Route;

/*
 | Panel uçları öğrenci API'sinden ayrı prefix ve ayrı guard'da.
 | Bir yetki hatasının öğrenci token'ıyla panele düşmesi imkânsız olmalı.
 */
Route::prefix('admin/v1')->group(function (): void {
    Route::post('auth/login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('admin.login');

    Route::middleware('auth:admin')->group(function (): void {
        Route::get('me', [AdminAuthController::class, 'me'])->name('admin.me');

        // Okuma: içerik gören her rol
        Route::get('courses', [ContentController::class, 'courses'])->name('admin.courses');
        Route::get('courses/{course}/units', [ContentController::class, 'units'])->name('admin.units');
        Route::get('units/{unit}/nodes', [ContentController::class, 'unitNodes'])->name('admin.units.nodes');
        Route::get('courses/{course}/topics', [ContentController::class, 'topics'])->name('admin.topics');
        Route::get('unit-templates', [ContentController::class, 'templates'])->name('admin.templates');
        Route::get('nodes/{node}/preview-selection', [ContentController::class, 'previewSelection'])
            ->name('admin.nodes.preview');
        Route::get('units/{unit}/exercises', [ExerciseController::class, 'index'])->name('admin.exercises.index');
        Route::get('exercises/{exercise}', [ExerciseController::class, 'show'])->name('admin.exercises.show');

        // Yazma: yalnızca editör
        Route::middleware('admin.can:edit')->group(function (): void {
            Route::post('units', [ContentController::class, 'storeUnit'])->name('admin.units.store');
            Route::post('content/import', [ContentController::class, 'importPackage'])->name('admin.content.import');

            // Soru düzenleme — içerik ekibinin günlük işi.
            Route::post('exercises', [ExerciseController::class, 'store'])->name('admin.exercises.store');
            Route::patch('exercises/{exercise}', [ExerciseController::class, 'update'])->name('admin.exercises.update');
            // Silmez, arşivler: yayınlanmış soru geçmiş oturumlarda referanslı.
            Route::delete('exercises/{exercise}', [ExerciseController::class, 'destroy'])->name('admin.exercises.destroy');
        });

        // Yayınlama: yalnızca denetçi — dört göz ilkesi
        Route::middleware('admin.can:publish')->group(function (): void {
            Route::post('units/{unit}/publish', [ContentController::class, 'publishUnit'])->name('admin.units.publish');
        });

        // Yönetici hesapları ve müfredat eşlemesi: yalnızca süper yönetici
        Route::middleware('admin.can:curriculum')->group(function (): void {
            Route::get('admins', [AdminUserController::class, 'index'])->name('admin.admins.index');
            Route::post('admins', [AdminUserController::class, 'store'])->name('admin.admins.store');
            Route::patch('admins/{admin}', [AdminUserController::class, 'update'])->name('admin.admins.update');

            Route::get('curriculum/options', [CurriculumMapController::class, 'options'])
                ->name('admin.curriculum.options');
            Route::get('exam-variants/{variant}/courses', [CurriculumMapController::class, 'show'])
                ->name('admin.curriculum.show');
            Route::put('exam-variants/{variant}/courses', [CurriculumMapController::class, 'update'])
                ->name('admin.curriculum.update');
        });
    });
});
