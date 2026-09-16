<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure\Eloquent\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property string $provider_event_id
 * @property string $type
 * @property int|null $user_id
 * @property array<array-key, mixed> $payload
 * @property CarbonImmutable|null $processed_at
 * @property string|null $error
 * @property CarbonImmutable $received_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionEventLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionEventLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionEventLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionEventLog whereError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionEventLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionEventLog wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionEventLog whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionEventLog whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionEventLog whereProviderEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionEventLog whereReceivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionEventLog whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SubscriptionEventLog whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class SubscriptionEventLog extends Model
{
    protected $table = 'subscription_events';

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
        ];
    }
}
