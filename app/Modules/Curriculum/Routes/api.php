<?php

declare(strict_types=1);

use App\Modules\Curriculum\Http\Controller\Api\V1\ExamCatalogController;
use App\Modules\Curriculum\Http\Controller\Api\V1\LearnerCourseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    // Kayıt akışı misafir oturumuyla yürüyor; uç yine de kimlik istiyor
    // çünkü sınav listesi anonim bir kataloğa açılacak kadar genel değil.
    Route::get('exams', [ExamCatalogController::class, 'index'])->name('exams.index');
    Route::get('me/courses', [LearnerCourseController::class, 'index'])->name('me.courses');
});
