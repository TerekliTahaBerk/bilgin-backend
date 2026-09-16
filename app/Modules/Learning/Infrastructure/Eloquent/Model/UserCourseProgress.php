<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property int $level
 * @property int $xp
 * @property int $completed_units
 * @property int $total_units
 * @property CarbonImmutable|null $last_studied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCourseProgress newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCourseProgress newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCourseProgress query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCourseProgress whereCompletedUnits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCourseProgress whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCourseProgress whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCourseProgress whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCourseProgress whereLastStudiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCourseProgress whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCourseProgress whereTotalUnits($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCourseProgress whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCourseProgress whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserCourseProgress whereXp($value)
 *
 * @mixin \Eloquent
 */
final class UserCourseProgress extends Model
{
    protected $table = 'user_course_progress';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_studied_at' => 'immutable_datetime'];
    }
}
