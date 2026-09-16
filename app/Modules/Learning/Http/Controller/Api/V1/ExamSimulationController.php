<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Controller\Api\V1;

use App\Modules\Catalog\Domain\Blueprint\BlueprintSpec;
use App\Modules\Catalog\Domain\Contract\BlueprintReader;
use App\Modules\Learning\Application\UseCase\StartExamSimulation;
use App\Modules\Learning\Http\Resource\SessionResource;
use App\Shared\Domain\Hearts\HeartBalanceReader;
use App\Shared\Domain\Learner\LearnerProfileReader;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ExamSimulationController extends ApiController
{
    /** Öğrencinin alanına uygun denemeler. */
    public function index(Request $request, BlueprintReader $blueprints, LearnerProfileReader $learners): JsonResponse
    {
        $learner = $learners->for($this->userId($request));

        if (! $learner->hasEnrollment()) {
            return ApiResponse::error(
                'ONBOARDING_REQUIRED',
                'Önce hangi sınava hazırlandığını seçmen gerekiyor.',
                409,
            );
        }

        $specs = $blueprints->forExamVariant((int) $learner->primaryExamVariantId);

        return ApiResponse::data(array_map(
            static fn (BlueprintSpec $b): array => [
                'id' => $b->id,
                'code' => $b->code,
                'name' => $b->name,
                'duration_min' => $b->durationMin,
                'question_count' => $b->totalQuestions(),
                // İstemci "yanlış 4 doğruyu götürür" uyarısını buradan yazar.
                'penalty_ratio' => $b->penaltyRatio,
            ],
            $specs,
        ));
    }

    /**
     * Deneme başlatır.
     *
     * Normal turdan ayrı uç: can harcamaz, üniteye bağlı değildir ve
     * sonucu doğruluk değil nettir. Tek uca sıkıştırmak, istemciyi her
     * çağrıda "hangi tür tur?" diye dallanmaya zorlardı.
     */
    public function store(Request $request, StartExamSimulation $start): JsonResponse
    {
        $data = $request->validate([
            'blueprint_id' => ['required', 'integer', 'exists:exam_blueprints,id'],
        ]);

        $userId = $this->userId($request);

        $session = $start($userId, (int) $data['blueprint_id'], $request->header('Idempotency-Key'));

        return ApiResponse::data(
            SessionResource::toArray($session, app(HeartBalanceReader::class)->balanceFor($userId)),
            201,
        );
    }
}
