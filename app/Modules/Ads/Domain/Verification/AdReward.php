<?php

declare(strict_types=1);

namespace App\Modules\Ads\Domain\Verification;

use App\Modules\Ads\Domain\Enum\AdPlacement;

/**
 * Doğrulanmış reklam ödülü.
 *
 * Bu nesne ancak AdMob'un imzası doğrulandıktan SONRA üretilir. İstemcinin
 * "reklamı izledim" demesi tek başına ödül üretmez — aksi hâlde reklam
 * izlemeden can kazanmak bir HTTP isteği kadar kolay olurdu.
 */
final readonly class AdReward
{
    public function __construct(
        public string $transactionId,
        public string $providerUserId,
        public AdPlacement $placement,
        public int $amount,
        public ?string $rewardItem,
        public ?string $adUnit,
    ) {}
}
