<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controller\Api\V1;

use App\Modules\Curriculum\Domain\Enum\FieldCode;
use App\Modules\Identity\Application\DTO\OnboardingCommand;
use App\Modules\Identity\Application\UseCase\ChangeExamField;
use App\Modules\Identity\Application\UseCase\CompleteOnboarding;
use App\Modules\Identity\Domain\Enum\DailyGoal;
use App\Modules\Identity\Domain\Enum\Grade;
use App\Modules\Identity\Http\Request\ChangeFieldRequest;
use App\Modules\Identity\Http\Request\OnboardingRequest;
use App\Modules\Identity\Http\Resource\UserResource;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

final class OnboardingController extends ApiController
{
    public function store(OnboardingRequest $request, CompleteOnboarding $complete): JsonResponse
    {
        $user = $complete(new OnboardingCommand(
            userId: $this->userId($request),
            examCode: $request->string('exam_code')->toString(),
            field: FieldCode::from($request->string('field')->toString()),
            grade: $request->filled('grade') ? Grade::from($request->string('grade')->toString()) : null,
            targetExamYear: $request->integer('target_exam_year') ?: null,
            targetExamMonth: $request->integer('target_exam_month') ?: null,
            name: $request->string('name')->toString() ?: null,
            avatarKey: $request->string('avatar_key')->toString() ?: null,
            acquisitionSource: $request->string('acquisition_source')->toString() ?: null,
            dailyGoal: DailyGoal::from($request->integer('daily_goal_rounds') ?: 3),
            reminderEnabled: $request->boolean('reminder_enabled', true),
            reminderTime: $request->string('reminder_time')->toString() ?: null,
        ));

        return ApiResponse::data([
            'user' => new UserResource($user),
            'next_step' => $user->profile?->placement_completed_at === null ? 'placement' : 'home',
        ], 201);
    }

    /**
     * Alan değiştirme. Tasarımda ayrı bir ekran yok ama ürün gerçeği bu:
     * öğrenciler Kasım'da alan değiştiriyor ve ilerlemelerini kaybetmemeli.
     */
    public function changeField(ChangeFieldRequest $request, ChangeExamField $change): JsonResponse
    {
        $enrollment = $change(
            $this->userId($request),
            FieldCode::from($request->string('field')->toString()),
        );

        $variant = $enrollment->examVariant;

        return ApiResponse::data([
            'exam_variant' => [
                'code' => $variant->code,
                'name' => $variant->name,
                'field' => $variant->field_code->value,
            ],
            // İlerleme course_id'ye bağlı; alan değişimi hiçbir şeyi silmez.
            'progress_preserved' => true,
        ]);
    }
}
