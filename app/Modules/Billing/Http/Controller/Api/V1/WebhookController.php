<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controller\Api\V1;

use App\Modules\Billing\Application\UseCase\ProcessSubscriptionEvent;
use App\Modules\Billing\Domain\Contract\SubscriptionGateway;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

final class WebhookController extends Controller
{
    public function revenuecat(
        Request $request,
        SubscriptionGateway $gateway,
        ProcessSubscriptionEvent $process,
    ): JsonResponse {
        if (! $gateway->verifySignature((string) $request->header('Authorization'))) {
            // Ayrıntı verilmez: geçerli imzayı arayan birine geri bildirim olur.
            Log::warning('RevenueCat webhook imza doğrulaması başarısız', ['ip' => $request->ip()]);

            return ApiResponse::error('UNAUTHORIZED', 'Geçersiz imza.', 401);
        }

        $payload = $request->all();
        $result = $process($gateway->parse($payload), $payload);

        // Her durumda 200: sağlayıcıya "aldım" demek, saatlerce süren
        // yeniden deneme kuyruğunu önler. Sorunlar log'da ve panelde görünür.
        return ApiResponse::data(['outcome' => $result->outcome, 'detail' => $result->detail]);
    }
}
