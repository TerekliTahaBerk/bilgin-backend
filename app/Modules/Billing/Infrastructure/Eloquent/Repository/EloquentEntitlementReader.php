<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Eloquent\Repository;

use App\Modules\Billing\Infrastructure\Eloquent\Model\Subscription;
use App\Shared\Clock\ClockInterface;
use App\Shared\Domain\Entitlement\EntitlementReader;
use App\Shared\Domain\Entitlement\Entitlements;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * FreeEntitlementReader'ın yerini alır — son geçici bağlama da kalktı.
 *
 * Premium durumu YALNIZCA buradan belirlenir. İstemcinin "ben premium'um"
 * demesi hiçbir kapıyı açmaz; kaynak, sağlayıcı webhook'uyla yazılmış
 * abonelik kaydıdır.
 *
 * Cache kısa (5 dk): oturum akışı her cevapta can politikasını sorguluyor,
 * ama abonelik iptali de makul sürede yansımalı.
 */
final readonly class EloquentEntitlementReader implements EntitlementReader
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private Cache $cache,
        private ClockInterface $clock,
    ) {}

    public function for(int $userId): Entitlements
    {
        $plan = $this->cache->remember(
            self::cacheKey($userId),
            self::CACHE_TTL_SECONDS,
            fn (): ?string => $this->activePlan($userId),
        );

        return $plan === null ? Entitlements::free() : Entitlements::premium($plan);
    }

    public static function cacheKey(int $userId): string
    {
        return "entitlements:{$userId}";
    }

    private function activePlan(int $userId): ?string
    {
        $subscription = Subscription::query()
            ->where('user_id', $userId)
            ->orderByDesc('current_period_end')
            ->get()
            ->first(fn (Subscription $s): bool => $this->grantsAccess($s));

        return $subscription?->plan->value;
    }

    private function grantsAccess(Subscription $subscription): bool
    {
        if (! $subscription->status->grantsAccess()) {
            return false;
        }

        // İptal edilmiş abonelik ödenen dönemin sonuna kadar geçerlidir;
        // dönem bitince durum güncellenmemiş olsa bile erişim kapanmalı.
        return $subscription->current_period_end === null
            || $subscription->current_period_end->getTimestamp() > $this->clock->now()->getTimestamp();
    }
}
