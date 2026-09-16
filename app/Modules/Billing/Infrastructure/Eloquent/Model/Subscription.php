<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Eloquent\Model;

use App\Modules\Billing\Domain\Enum\SubscriptionPlan;
use App\Modules\Billing\Domain\Enum\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string|null $provider_subscription_id
 * @property string $product_id
 * @property SubscriptionPlan $plan
 * @property SubscriptionStatus $status
 * @property string|null $store
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $current_period_end
 * @property CarbonImmutable|null $trial_end
 * @property CarbonImmutable|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCancelledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereCurrentPeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription wherePlan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereProviderSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereStore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereTrialEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class Subscription extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'plan' => SubscriptionPlan::class,
            'status' => SubscriptionStatus::class,
            'started_at' => 'immutable_datetime',
            'current_period_end' => 'immutable_datetime',
            'trial_end' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
