<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $platform
 * @property string|null $push_token
 * @property string|null $device_identifier
 * @property string|null $app_version
 * @property CarbonImmutable|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereAppVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereDeviceIdentifier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereLastSeenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device wherePlatform($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device wherePushToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class Device extends Model
{
    protected $fillable = [
        'user_id', 'platform', 'push_token', 'device_identifier', 'app_version', 'last_seen_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_seen_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
