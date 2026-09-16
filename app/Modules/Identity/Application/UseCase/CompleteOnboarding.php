<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCase;

use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamVariant;
use App\Modules\Identity\Application\DTO\OnboardingCommand;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Shared\Clock\ClockInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Onboarding'in 7 adımını tek bir kayda çevirir.
 *
 * İşin özü tek satırda: sınav kodu + alan kodu → tek bir ExamVariant.
 * Ders listesi, sekmeler ve paywall bundan sonra tamamen veriden türer;
 * "eğer Sayısal ise şu dersleri göster" diye bir kod hiçbir yerde yoktur.
 */
final readonly class CompleteOnboarding
{
    public function __construct(private ClockInterface $clock) {}

    public function __invoke(OnboardingCommand $command): User
    {
        return DB::transaction(function () use ($command): User {
            $user = User::query()->findOrFail($command->userId);
            $variant = $this->resolveVariant($command->examCode, $command->field->value);

            $user->update(array_filter([
                'name' => $command->name,
                'avatar_key' => $command->avatarKey,
            ], static fn ($v): bool => $v !== null) + ['last_active_at' => $this->clock->now()]);

            $user->profile()->updateOrCreate(['user_id' => $user->id], [
                'grade' => $command->grade,
                'target_exam_year' => $command->targetExamYear,
                'target_exam_month' => $command->targetExamMonth,
                'daily_goal_rounds' => $command->dailyGoal,
                'reminder_enabled' => $command->reminderEnabled,
                'reminder_time' => $command->reminderTime,
                'acquisition_source' => $command->acquisitionSource,
                'onboarding_completed_at' => $this->clock->now(),
            ]);

            $user->enrollments()->updateOrCreate(
                ['user_id' => $user->id, 'exam_variant_id' => $variant->id],
                ['is_primary' => true, 'enrolled_at' => $this->clock->now()],
            );

            $user->load(['profile', 'enrollments.examVariant']);

            return $user;
        });
    }

    private function resolveVariant(string $examCode, string $fieldCode): ExamVariant
    {
        return ExamVariant::query()
            ->where('field_code', $fieldCode)
            ->whereHas('exam', fn ($q) => $q->where('code', $examCode)->where('is_active', true))
            ->where('is_active', true)
            ->first()
            ?? throw new RuntimeException("Bu sınav/alan için varyant yok: {$examCode}/{$fieldCode}");
    }
}
