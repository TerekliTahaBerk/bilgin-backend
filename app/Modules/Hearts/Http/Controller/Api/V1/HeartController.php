<?php

declare(strict_types=1);

namespace App\Modules\Hearts\Http\Controller\Api\V1;

use App\Shared\Domain\Hearts\HeartBalanceReader;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use App\Shared\Http\Resource\HeartResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HeartController extends ApiController
{
    public function show(Request $request, HeartBalanceReader $hearts): JsonResponse
    {
        return ApiResponse::data(
            HeartResource::toArray($hearts->balanceFor($this->userId($request)))
        );
    }
}
