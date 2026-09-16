<?php

declare(strict_types=1);

use App\Modules\Learning\Http\Controller\Api\V1\ExamSimulationController;
use App\Modules\Learning\Http\Controller\Api\V1\StudySessionController;
use App\Modules\Learning\Http\Controller\Api\V1\TopicAnalysisController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('me/topics', [TopicAnalysisController::class, 'index'])->name('me.topics');

    Route::post('sessions', [StudySessionController::class, 'store'])->name('sessions.store');

    // Deneme: ayrı uç, aynı oturum akışı. Cevaplama ve tamamlama
    // /sessions/{session}/* üzerinden devam eder.
    Route::get('exam-simulations', [ExamSimulationController::class, 'index'])->name('exam-simulations.index');
    Route::post('exam-simulations', [ExamSimulationController::class, 'store'])->name('exam-simulations.store');

    // Cevap gönderimi sık ama sınırlı: 7 soruluk bir tur ~7 istek.
    // 120/dk hem rahat hem bot davranışına karşı bir tavan.
    Route::post('sessions/{session}/answers', [StudySessionController::class, 'answer'])
        ->middleware('throttle:120,1')
        ->name('sessions.answer');

    Route::post('sessions/{session}/complete', [StudySessionController::class, 'complete'])->name('sessions.complete');
    Route::post('sessions/{session}/abandon', [StudySessionController::class, 'abandon'])->name('sessions.abandon');
});
