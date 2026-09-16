<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $unit_id
 * @property int $course_id
 * @property int $completion_percent
 * @property int $completed_nodes
 * @property int $total_nodes
 * @property CarbonImmutable|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUnitProgress newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUnitProgress newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUnitProgress query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUnitProgress whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUnitProgress whereCompletedNodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUnitProgress whereCompletionPercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUnitProgress whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUnitProgress whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUnitProgress whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUnitProgress whereTotalNodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUnitProgress whereUnitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUnitProgress whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserUnitProgress whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class UserUnitProgress extends Model
{
    protected $table = 'user_unit_progress';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['completed_at' => 'immutable_datetime'];
    }
}
