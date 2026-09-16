<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Http\Controller\Api\V1;

use App\Modules\Curriculum\Application\UseCase\GetLearnerCourses;
use App\Modules\Curriculum\Http\Resource\LearnerCoursesResource;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamVariant;
use App\Shared\Domain\Entitlement\EntitlementReader;
use App\Shared\Domain\Learner\LearnerProfileReader;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LearnerCourseController extends ApiController
{
    /** GET /v1/me/courses — tasarımdaki TYT / AYT sekmeleri. */
    public function index(
        Request $request,
        GetLearnerCourses $getCourses,
        LearnerProfileReader $learners,
        EntitlementReader $entitlements,
    ): JsonResponse {
        $userId = $this->userId($request);
        $learner = $learners->for($userId);

        // Identity'nin User modeline dokunmadan: yalnızca yayımlanmış profil.
        if (! $learner->hasEnrollment()) {
            return ApiResponse::error(
                'ONBOARDING_REQUIRED',
                'Önce hangi sınava hazırlandığını seçmen gerekiyor.',
                409,
            );
        }

        $variant = ExamVariant::query()->findOrFail($learner->primaryExamVariantId);

        return ApiResponse::data(LearnerCoursesResource::toArray(
            $getCourses($variant, $entitlements->for($userId)->premium)
        ));
    }
}
