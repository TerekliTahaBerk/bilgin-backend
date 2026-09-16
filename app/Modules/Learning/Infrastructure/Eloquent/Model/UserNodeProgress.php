<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $unit_node_id
 * @property int $unit_id
 * @property int $course_id
 * @property bool $completed
 * @property int $best_accuracy
 * @property int $attempts
 * @property bool $is_perfect
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $last_attempt_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress whereBestAccuracy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress whereCompleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress whereIsPerfect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress whereLastAttemptAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress whereUnitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress whereUnitNodeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserNodeProgress whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class UserNodeProgress extends Model
{
    protected $table = 'user_node_progress';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['completed' => 'boolean',
            'is_perfect' => 'boolean',
            'completed_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime', ];
    }
}
