<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent\Repository;

use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Shared\Domain\Learner\LearnerProfile;
use App\Shared\Domain\Learner\LearnerProfileReader;

/** Identity'nin dışarıya açtığı tek okuma yüzeyi. */
final class EloquentLearnerProfileReader implements LearnerProfileReader
{
    public function for(int $userId): LearnerProfile
    {
        $user = User::query()->with(['profile', 'enrollments'])->find($userId);

        if ($user === null) {
            return new LearnerProfile($userId);
        }

        $profile = $user->profile;
        $enrollment = $user->enrollments->firstWhere('is_primary', true);

        return new LearnerProfile(
            userId: $userId,
            gradeLevel: $profile?->grade?->curriculumLevel(),
            targetExamYear: $profile?->target_exam_year,
            timezone: $user->timezone,
            primaryExamVariantId: $enrollment?->exam_variant_id,
        );
    }
}
