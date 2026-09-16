<?php

declare(strict_types=1);

namespace App\Modules\Ads\Infrastructure\Eloquent\Model;

use App\Modules\Ads\Domain\Enum\AdPlacement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $network
 * @property AdPlacement $placement
 * @property string $transaction_id
 * @property string|null $ad_unit
 * @property int $reward_amount
 * @property string|null $reward_item
 * @property CarbonImmutable|null $granted_at
 * @property string|null $rejection_reason
 * @property CarbonImmutable $received_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdRewardLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdRewardLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdRewardLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdRewardLog whereAdUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdRewardLog whereGrantedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdRewardLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdRewardLog whereNetwork($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdRewardLog wherePlacement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdRewardLog whereReceivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdRewardLog whereRejectionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdRewardLog whereRewardAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdRewardLog whereRewardItem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdRewardLog whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdRewardLog whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class AdRewardLog extends Model
{
    protected $table = 'ad_rewards';

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'placement' => AdPlacement::class,
            'granted_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
        ];
    }
}
