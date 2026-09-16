<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $topic_id
 * @property int $attempts
 * @property int $correct
 * @property int $accuracy
 * @property string $mastery
 * @property CarbonImmutable|null $last_practiced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopicStat newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopicStat newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopicStat query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopicStat whereAccuracy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopicStat whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopicStat whereCorrect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopicStat whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopicStat whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopicStat whereLastPracticedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopicStat whereMastery($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopicStat whereTopicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopicStat whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTopicStat whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class UserTopicStat extends Model
{
    protected $table = 'user_topic_stats';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_practiced_at' => 'immutable_datetime'];
    }
}
