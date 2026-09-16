<?php

declare(strict_types=1);

namespace App\Modules\Ads\Http\Controller\Api\V1;

use App\Modules\Ads\Application\UseCase\GrantAdReward;
use App\Modules\Ads\Domain\Verification\AdRewardVerifier;
use App\Modules\Ads\Domain\Verification\AdVerificationFailed;
use App\Shared\Domain\Entitlement\EntitlementReader;
use App\Shared\Domain\Learner\LearnerProfileReader;
use App\Shared\Http\ApiController;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class AdController extends ApiController
{
    /**
     * Reklam politikası — ne gösterileceğine SUNUCU karar verir.
     *
     * İstemcide "premium mi?" kontrolü yapmak, eski sürümlerin premium
     * kullanıcıya reklam göstermeye devam etmesi demek; uygulama
     * güncellemesi kullanıcı isteğine bağlıdır ve haftalar sürer.
     *
     * Aynı sebeple sıklık ayarları da burada: A/B testi için sunucudan
     * değiştirilebilmeli.
     */
    public function policy(
        Request $request,
        EntitlementReader $entitlements,
        LearnerProfileReader $learners,
        GrantAdReward $rewards,
    ): JsonResponse {
        $userId = $this->userId($request);
        $premium = $entitlements->for($userId)->premium;

        if ($premium) {
            // Premium'un en güçlü satış noktası: hiçbir reklam yok.
            return ApiResponse::data([
                'show_ads' => false,
                'rewarded' => ['enabled' => false, 'remaining_today' => 0],
                'interstitial' => ['enabled' => false],
            ]);
        }

        $timezone = $learners->for($userId)->timezone;
        $accountAgeDays = $this->accountAgeDays($userId);
        $graceDays = (int) config('tekrarla.ads.interstitial.grace_days');

        return ApiResponse::data([
            'show_ads' => true,
            'rewarded' => [
                'enabled' => true,
                'remaining_today' => $rewards->remainingToday($userId, $timezone),
                'placements' => $rewards->placements(),
            ],
            'interstitial' => [
                // İlk günlerde reklam yok: yeni kullanıcıyı reklamla
                // karşılamak, retention'ı en hızlı düşüren şeylerden biri.
                'enabled' => $accountAgeDays >= $graceDays,
                'every_n_sessions' => (int) config('tekrarla.ads.interstitial.every_n_sessions'),
                'min_interval_seconds' => (int) config('tekrarla.ads.interstitial.min_interval_seconds'),
            ],
            'banner' => ['enabled' => false],
        ]);
    }

    /**
     * AdMob sunucu tarafı doğrulama (SSV) callback'i.
     *
     * AdMob GET ile çağırır ve sorgu dizesini imzalar. Kullanıcı token'ı
     * YOKTUR — çağıran reklam ağı, kullanıcı değil.
     */
    public function ssv(Request $request, AdRewardVerifier $verifier, GrantAdReward $grant): JsonResponse
    {
        try {
            /*
             | HAM sorgu dizesi kullanılıyor — $request->getQueryString() DEĞİL.
             |
             | Symfony'nin getQueryString()'i parametreleri alfabetik sıralar.
             | AdMob ise imzayı kendi gönderdiği SIRA üzerinde hesaplıyor;
             | sıralanmış dizeyle doğrulamak geçerli callback'lerin tamamını
             | reddeder.
             */
            $reward = $verifier->verify(
                $request->query(),
                is_string($raw = $request->server('QUERY_STRING', '')) ? $raw : '',
            );
        } catch (AdVerificationFailed $e) {
            Log::warning('AdMob SSV doğrulaması başarısız', [
                'ip' => $request->ip(),
                'reason' => $e->getMessage(),
            ]);

            return ApiResponse::error('AD_VERIFICATION_FAILED', $e->getMessage(), 401);
        }

        $outcome = $grant($reward);

        // Her durumda 200: AdMob'a "aldım" demek, yeniden deneme
        // kuyruğunu önler. Reddedilme sebepleri kayıtta görünür.
        return ApiResponse::data(array_filter([
            'outcome' => $outcome->outcome,
            'detail' => $outcome->detail,
            'hearts' => $outcome->hearts,
            'remaining_today' => $outcome->remainingToday,
        ], static fn ($v): bool => $v !== null));
    }

    private function accountAgeDays(int $userId): int
    {
        $createdAt = DB::table('users')->where('id', $userId)->value('created_at');

        return $createdAt === null ? 0 : (int) now()->diffInDays($createdAt, absolute: true);
    }
}
