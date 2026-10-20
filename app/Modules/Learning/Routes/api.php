<?php

declare(strict_types=1);

use App\Modules\Learning\Http\Controller\Api\V1\ExamSimulationController;
use App\Modules\Learning\Http\Controller\Api\V1\PlacementController;
use App\Modules\Learning\Http\Controller\Api\V1\StudySessionController;
use App\Modules\Learning\Http\Controller\Api\V1\TopicAnalysisController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('me/topics', [TopicAnalysisController::class, 'index'])->name('me.topics');

    Route::post('sessions', [StudySessionController::class, 'store'])->name('sessions.store');

    /*
     | Seviye belirleme testi — kayıttan sonra, ilk turdan önce.
     |
     | Gönderim tek istekte 12 cevap taşıyor ve hayatta bir (nadiren iki)
     | kez çağrılıyor; cevap ucunun 120/dk sınırı buraya uymaz, 10/dk hem
     | yeterli hem tekrar gönderime karşı dar.
     */
    Route::get('placement', [PlacementController::class, 'show'])->name('placement.show');
    Route::post('placement', [PlacementController::class, 'store'])->name('placement.store');
    Route::post('placement/skip', [PlacementController::class, 'skip'])->name('placement.skip');
    Route::post('placement/{placement}/submit', [PlacementController::class, 'submit'])
        ->middleware('throttle:10,1')
        ->whereNumber('placement')
        ->name('placement.submit');

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
