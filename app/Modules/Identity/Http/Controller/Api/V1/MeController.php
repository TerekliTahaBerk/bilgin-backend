<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controller\Api\V1;

use App\Modules\Identity\Http\Resource\UserResource;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MeController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        $user = User::query()
            ->with(['profile', 'enrollments.examVariant'])
            ->findOrFail($this->userId($request));

        return ApiResponse::data(new UserResource($user));
    }
}
