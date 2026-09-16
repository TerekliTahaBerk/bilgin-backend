<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controller\Api\V1;

use App\Modules\Catalog\Application\UseCase\GetCoursePath;
use App\Modules\Catalog\Domain\Path\LearnerContext;
use App\Modules\Catalog\Http\Resource\CoursePathResource;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Shared\Clock\ClockInterface;
use App\Shared\Domain\Entitlement\EntitlementReader;
use App\Shared\Domain\Enum\PublishStatus;
use App\Shared\Domain\Learner\LearnerProfileReader;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CoursePathController extends ApiController
{
    /** GET /v1/courses/{course}/path — ünite yolu (imza ekran). */
    public function show(
        Request $request,
        Course $course,
        GetCoursePath $getPath,
        LearnerProfileReader $learners,
        EntitlementReader $entitlements,
        ClockInterface $clock,
    ): JsonResponse {
        if ($course->status !== PublishStatus::Published) {
            return ApiResponse::error('CONTENT_NOT_AVAILABLE', 'Bu ders henüz yayında değil.', 404);
        }

        $userId = $this->userId($request);
        $profile = $learners->for($userId);

        $learner = new LearnerContext(
            gradeLevel: $profile->gradeLevel,
            targetExamYear: $profile->targetExamYear,
            hasPremium: $entitlements->for($userId)->premium,
            currentYear: (int) $clock->now()->format('Y'),
        );

        return ApiResponse::data(CoursePathResource::toArray(
            $getPath($course->load('subject'), $userId, $learner)
        ));
    }
}
