<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controller\Api\V1;

use App\Modules\Billing\Infrastructure\Eloquent\Model\Subscription;
use App\Shared\Domain\Entitlement\EntitlementReader;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EntitlementController extends ApiController
{
    /** İstemci paywall'ı ve özellik kapılarını buna göre çizer. */
    public function show(Request $request, EntitlementReader $entitlements): JsonResponse
    {
        $userId = $this->userId($request);
        $entitlement = $entitlements->for($userId);

        $subscription = Subscription::query()
            ->where('user_id', $userId)
            ->orderByDesc('current_period_end')
            ->first();

        return ApiResponse::data([
            'premium' => [
                'active' => $entitlement->premium,
                'plan' => $entitlement->plan,
                'status' => $subscription?->status->value,
                'expires_at' => $subscription?->current_period_end?->format(DATE_ATOM),
                'in_trial' => $subscription?->trial_end !== null
                    && $subscription->trial_end->getTimestamp() > time(),
            ],
            'limits' => [
                'max_enrollments' => $entitlement->premium ? null : $entitlement->maxEnrollments,
                'unlimited_hearts' => $entitlement->premium,
                'ads' => ! $entitlement->premium,
            ],
        ]);
    }

    /**
     * Paywall ekranının karşılaştırma tablosu.
     *
     * Fiyat BURADA YOK: mağaza fiyatı ülkeye, para birimine ve kampanyaya
     * göre değişir ve tek doğru kaynağı store SDK'sıdır. Sunucu yalnızca
     * ürün kimliklerini ve neyin dahil olduğunu söyler.
     */
    public function offerings(): JsonResponse
    {
        return ApiResponse::data([
            'products' => [
                ['id' => config('tekrarla.billing.products.monthly'), 'plan' => 'monthly', 'label' => 'Aylık'],
                ['id' => config('tekrarla.billing.products.yearly'), 'plan' => 'yearly', 'label' => 'Yıllık', 'recommended' => true],
            ],
            'trial_days' => (int) config('tekrarla.billing.trial_days'),
            'comparison' => [
                ['feature' => 'Can', 'free' => '5, 18 dakikada 1 dolar', 'premium' => 'Sınırsız'],
                ['feature' => 'Reklam', 'free' => 'Var', 'premium' => 'Yok'],
                ['feature' => 'Sınav takibi', 'free' => '1 sınav', 'premium' => 'Sınırsız'],
                ['feature' => 'Dersler', 'free' => 'Temel', 'premium' => 'Tümü'],
                ['feature' => 'Yanlış tekrarı', 'free' => 'Günde 1', 'premium' => 'Sınırsız'],
                ['feature' => 'Konu analizi', 'free' => 'Özet', 'premium' => 'Detaylı'],
            ],
            // Tasarımdaki açık taahhüt; paywall'da aynen gösterilir.
            'not_for_sale' => ['XP', 'Seri', 'Lig sıralaması'],
        ]);
    }
}
