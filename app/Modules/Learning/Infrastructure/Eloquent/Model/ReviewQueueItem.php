<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $exercise_id
 * @property int $topic_id
 * @property int|null $unit_id
 * @property CarbonImmutable $due_at
 * @property int $interval_days
 * @property int $lapses
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewQueueItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewQueueItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewQueueItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewQueueItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewQueueItem whereDueAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewQueueItem whereExerciseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewQueueItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewQueueItem whereIntervalDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewQueueItem whereLapses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewQueueItem whereTopicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewQueueItem whereUnitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewQueueItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReviewQueueItem whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class ReviewQueueItem extends Model
{
    protected $table = 'review_queue';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['due_at' => 'immutable_datetime'];
    }
}
