<?php

declare(strict_types=1);

namespace App\Modules\Hearts\Infrastructure\Eloquent\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property int $hearts
 * @property CarbonImmutable $last_regen_at
 * @property CarbonImmutable|null $unlimited_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserHeart newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserHeart newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserHeart query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserHeart whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserHeart whereHearts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserHeart whereLastRegenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserHeart whereUnlimitedUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserHeart whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserHeart whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class UserHeart extends Model
{
    protected $table = 'user_hearts';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['user_id', 'hearts', 'last_regen_at', 'unlimited_until'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hearts' => 'integer',
            'last_regen_at' => 'immutable_datetime',
            'unlimited_until' => 'immutable_datetime',
        ];
    }
}
