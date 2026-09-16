<?php

declare(strict_types=1);

namespace App\Modules\League\Infrastructure\Eloquent\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $league_id
 * @property int $user_id
 * @property int $weekly_xp
 * @property int|null $final_rank
 * @property string|null $result
 * @property Carbon $week_start
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read League $league
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereFinalRank($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereLeagueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereResult($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereWeekStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereWeeklyXp($value)
 *
 * @mixin \Eloquent
 */
final class LeagueMembership extends Model
{
    protected $fillable = ['league_id', 'user_id', 'weekly_xp', 'final_rank', 'result', 'week_start'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['week_start' => 'date'];
    }

    /** @return BelongsTo<League, $this> */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }
}
