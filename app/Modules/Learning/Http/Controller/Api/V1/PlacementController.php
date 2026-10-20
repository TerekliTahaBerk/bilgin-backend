<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Controller\Api\V1;

use App\Modules\Learning\Application\UseCase\CompletePlacement;
use App\Modules\Learning\Application\UseCase\StartPlacement;
use App\Modules\Learning\Http\Request\SubmitPlacementRequest;
use App\Modules\Learning\Http\Resource\PlacementResource;
use App\Shared\Domain\Learner\PlacementRecorder;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Seviye belirleme testi.
 *
 * Kayıt akışının son adımı; `POST /v1/onboarding` yanıtındaki
 * `next_step: "placement"` tam olarak buraya işaret ediyordu ve bu uç
 * yazılana kadar istemcinin gidecek yeri yoktu.
 */
final class PlacementController extends ApiController
{
    /** Testi başlatır ya da yarıda kalmışı aynı sorularla geri verir. */
    public function store(Request $request, StartPlacement $start): JsonResponse
    {
        $attempt = $start($this->userId($request));

        return ApiResponse::data(
            ['placement' => PlacementResource::toArray($attempt)],
            $attempt->wasRecentlyCreated ? 201 : 200,
        );
    }

    /**
     * Tüm cevaplar TEK istekte.
     *
     * Turların aksine soru soru gönderilmiyor: seviye testinde anlık geri
     * bildirim YOK. Öğrenciye her soruda "yanlış" demek, ölçüm için
     * başlatılan bir akışı sıfırıncı dakikada moral kırıcı bir sınava
     * çevirirdi.
     */
    public function submit(
        SubmitPlacementRequest $request,
        int $placement,
        CompletePlacement $complete,
    ): JsonResponse {
        /** @var array<int|string, array<string, mixed>> $answers */
        $answers = $request->validated('answers');

        $result = $complete($this->userId($request), $placement, $answers);

        return ApiResponse::data(['result' => $result]);
    }

    public function skip(Request $request, CompletePlacement $complete): JsonResponse
    {
        $complete->skip($this->userId($request));

        return ApiResponse::data(['skipped' => true]);
    }

    /** İstemcinin akışı yeniden kurabilmesi için durum. */
    public function show(Request $request, PlacementRecorder $placement): JsonResponse
    {
        return ApiResponse::data([
            'completed' => $placement->hasCompleted($this->userId($request)),
        ]);
    }
}
