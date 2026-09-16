<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Controller\Api\V1;

use App\Modules\Learning\Application\DTO\StartSessionCommand;
use App\Modules\Learning\Application\DTO\SubmitAnswerCommand;
use App\Modules\Learning\Application\UseCase\CompleteSession;
use App\Modules\Learning\Application\UseCase\StartStudySession;
use App\Modules\Learning\Application\UseCase\SubmitAnswer;
use App\Modules\Learning\Domain\Exception\LearningException;
use App\Modules\Learning\Http\Request\StartSessionRequest;
use App\Modules\Learning\Http\Request\SubmitAnswerRequest;
use App\Modules\Learning\Http\Resource\AnswerResource;
use App\Modules\Learning\Http\Resource\SessionResource;
use App\Modules\Learning\Http\Resource\SessionSummaryResource;
use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use App\Shared\Domain\Hearts\HeartBalanceReader;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StudySessionController extends ApiController
{
    public function store(
        StartSessionRequest $request,
        StartStudySession $start,
        HeartBalanceReader $hearts,
    ): JsonResponse {
        $userId = $this->userId($request);

        $session = $start(new StartSessionCommand(
            userId: $userId,
            nodeId: $request->integer('node_id'),
            idempotencyKey: $request->header('Idempotency-Key'),
        ));

        return ApiResponse::data(
            SessionResource::toArray($session, $hearts->balanceFor($userId)),
            201,
        );
    }

    public function answer(
        SubmitAnswerRequest $request,
        string $session,
        SubmitAnswer $submit,
    ): JsonResponse {
        $outcome = $submit(new SubmitAnswerCommand(
            userId: $this->userId($request),
            sessionUuid: $session,
            exerciseId: $request->integer('exercise_id'),
            answer: (array) $request->input('answer'),
            elapsedMs: $request->integer('elapsed_ms'),
        ));

        return ApiResponse::data(AnswerResource::toArray($outcome));
    }

    public function complete(Request $request, string $session, CompleteSession $complete): JsonResponse
    {
        $summary = $complete($this->userId($request), $session);

        return ApiResponse::data(SessionSummaryResource::toArray($summary));
    }

    public function abandon(Request $request, string $session): JsonResponse
    {
        $record = StudySession::query()
            ->where('uuid', $session)
            ->where('user_id', $this->userId($request))
            ->first();

        if ($record === null) {
            throw LearningException::sessionNotFound();
        }

        // Harcanan can iade edilmez: yarım bırakmak bir kaçış yolu olmamalı.
        $record->isActive() && $record->update(['status' => 'abandoned']);

        return ApiResponse::data(['status' => $record->status]);
    }
}
