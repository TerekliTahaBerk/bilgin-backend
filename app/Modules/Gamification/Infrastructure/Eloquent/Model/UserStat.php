<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Infrastructure\Eloquent\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property int $total_xp
 * @property int $level
 * @property int $current_streak
 * @property int $longest_streak
 * @property Carbon|null $last_study_date
 * @property int $total_sessions
 * @property int $perfect_sessions
 * @property int $total_correct
 * @property int $total_study_seconds
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStat query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStat whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStat whereCurrentStreak($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStat whereLastStudyDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStat whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStat whereLongestStreak($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStat wherePerfectSessions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStat whereTotalCorrect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStat whereTotalSessions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStat whereTotalStudySeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStat whereTotalXp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStat whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserStat whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class UserStat extends Model
{
    protected $table = 'user_stats';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'total_xp' => 0,
        'level' => 1,
        'current_streak' => 0,
        'longest_streak' => 0,
        'total_sessions' => 0,
        'perfect_sessions' => 0,
        'total_correct' => 0,
        'total_study_seconds' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_study_date' => 'date'];
    }
}
