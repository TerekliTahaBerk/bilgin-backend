<?php

declare(strict_types=1);

namespace App\Modules\Ads\Application\UseCase;

use App\Modules\Ads\Domain\Enum\AdPlacement;
use App\Modules\Ads\Domain\Verification\AdReward;
use App\Modules\Ads\Infrastructure\Eloquent\Model\AdRewardLog;
use App\Modules\Hearts\Application\UseCase\HeartService;
use App\Modules\Hearts\Domain\Enum\HeartReason;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Shared\Clock\ClockInterface;
use App\Shared\Domain\Entitlement\EntitlementReader;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Doğrulanmış reklam ödülünü verir.
 *
 * Günlük limit ürünün can damarı: reklamla can sınırsız olsaydı premium'un
 * değeri sıfırlanırdı. Limit, kullanıcının YEREL gününe göre sayılır — UTC
 * kullanmak, İstanbul'da gece çalışan öğrenciye günde iki kez limit verirdi.
 *
 * Her callback kaydedilir, verilmese bile: "reklamı izledim ama can gelmedi"
 * şikâyetinin cevabı ancak reddedilme sebebiyle birlikte verilebilir.
 */
final readonly class GrantAdReward
{
    public function __construct(
        private ClockInterface $clock,
        private HeartService $hearts,
        private EntitlementReader $entitlements,
    ) {}

    public function __invoke(AdReward $reward): RewardOutcome
    {
        $now = $this->clock->now();

        // transaction_id tekilliği: aynı callback iki kez ödül üretemez.
        $inserted = DB::table('ad_rewards')->insertOrIgnore([
            'transaction_id' => $reward->transactionId,
            'network' => 'admob',
            'placement' => $reward->placement->value,
            'ad_unit' => $reward->adUnit,
            'reward_amount' => $reward->amount,
            'reward_item' => $reward->rewardItem,
            // user_id aşağıda çözülüyor: callback'i önce kaydetmek,
            // kullanıcıyı bulamasak bile izin kalmasını sağlıyor.
            'received_at' => $now,
        ]);

        if ($inserted === 0) {
            return RewardOutcome::duplicate();
        }

        $log = AdRewardLog::query()->where('transaction_id', $reward->transactionId)->firstOrFail();

        $user = Str::isUuid($reward->providerUserId)
            ? User::query()->where('uuid', $reward->providerUserId)->first()
            : null;

        if ($user === null) {
            $log->update(['rejection_reason' => 'user_not_found']);

            return RewardOutcome::rejected('Kullanıcı bulunamadı.');
        }

        $log->update(['user_id' => $user->id]);

        // Premium'un zaten sınırsız canı var; ödül vermek anlamsız ama
        // hata da değil — istemci yanlışlıkla reklam göstermiş olabilir.
        if ($this->entitlements->for((int) $user->id)->premium) {
            $log->update(['rejection_reason' => 'already_premium']);

            return RewardOutcome::rejected('Premium kullanıcının canı zaten sınırsız.');
        }

        $used = $this->todaysRewards((int) $user->id, $user->timezone);
        $limit = (int) config('tekrarla.hearts.ad_reward_daily_limit', 4);

        if ($used >= $limit) {
            $log->update(['rejection_reason' => 'daily_limit']);

            return RewardOutcome::rejected("Günlük reklam ödülü limitine ulaşıldı ({$limit}).");
        }

        $balance = $this->hearts->grant(
            (int) $user->id,
            HeartReason::AdReward,
            "ad:{$reward->transactionId}",
        );

        $log->update(['granted_at' => $now]);

        return RewardOutcome::granted(
            hearts: $balance->hearts,
            remainingToday: max(0, $limit - $used - 1),
        );
    }

    /**
     * Kullanıcının bugünkü ödül sayısı — YEREL güne göre.
     *
     * UTC'ye göre saymak, saat dilimi farkı yüzünden bazı kullanıcılara
     * günde iki kez limit açardı.
     */
    private function todaysRewards(int $userId, string $timezone): int
    {
        $localMidnight = $this->clock->now()
            ->setTimezone(new DateTimeZone($timezone))
            ->setTime(0, 0)
            ->setTimezone(new DateTimeZone('UTC'));

        return AdRewardLog::query()
            ->where('user_id', $userId)
            ->whereNotNull('granted_at')
            ->where('granted_at', '>=', $localMidnight)
            ->count();
    }

    /** Profil/ayarlar ekranı için: bugün kaç ödül hakkı kaldı? */
    public function remainingToday(int $userId, string $timezone): int
    {
        $limit = (int) config('tekrarla.hearts.ad_reward_daily_limit', 4);

        return max(0, $limit - $this->todaysRewards($userId, $timezone));
    }

    /** @return list<string> */
    public function placements(): array
    {
        return array_map(static fn (AdPlacement $p): string => $p->value, AdPlacement::cases());
    }
}
