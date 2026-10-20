<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controller\Api\V1;

use App\Modules\Curriculum\Domain\Enum\FieldCode;
use App\Modules\Identity\Application\UseCase\EnrollInExam;
use App\Modules\Identity\Http\Request\EnrollInExamRequest;
use App\Modules\Identity\Http\Resource\EnrollmentResource;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Öğrencinin kayıtlı olduğu sınavlar.
 *
 * Çoğul olması uç bir durum değil: TYT/AYT hazırlanan bir öğrenci aynı yıl
 * KPSS'ye ya da bir dil sınavına da girebiliyor ve ikisinin ilerlemesi
 * birbirine karışmamalı.
 */
final class EnrollmentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = User::query()
            ->with(['enrollments.examVariant.exam'])
            ->findOrFail($this->userId($request));

        return ApiResponse::data([
            'enrollments' => $user->enrollments
                ->map(EnrollmentResource::toArray(...))
                ->values()
                ->all(),
        ]);
    }

    public function store(EnrollInExamRequest $request, EnrollInExam $enroll): JsonResponse
    {
        $enrollment = $enroll(
            $this->userId($request),
            $request->string('exam_code')->toString(),
            FieldCode::from($request->string('field')->toString()),
        );

        return ApiResponse::data(
            ['enrollment' => EnrollmentResource::toArray($enrollment)],
            201,
        );
    }

    /** Sınavlar arası geçiş: hangi kayıt birincilse uygulama onu gösteriyor. */
    public function makePrimary(Request $request, int $enrollment, EnrollInExam $enroll): JsonResponse
    {
        $switched = $enroll->switchTo($this->userId($request), $enrollment);

        return ApiResponse::data(['enrollment' => EnrollmentResource::toArray($switched)]);
    }
}
