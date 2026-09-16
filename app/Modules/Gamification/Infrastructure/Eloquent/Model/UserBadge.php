<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Infrastructure\Eloquent\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $badge_id
 * @property CarbonImmutable $earned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBadge newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBadge newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBadge query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBadge whereBadgeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBadge whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBadge whereEarnedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBadge whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBadge whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserBadge whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class UserBadge extends Model
{
    protected $fillable = ['user_id', 'badge_id', 'earned_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['earned_at' => 'immutable_datetime'];
    }
}
