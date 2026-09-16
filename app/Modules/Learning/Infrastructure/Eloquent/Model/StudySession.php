<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $unit_node_id
 * @property int|null $course_id
 * @property int|null $unit_id
 * @property string $status
 * @property bool $consumes_hearts
 * @property int|null $time_limit_sec
 * @property int $correct_count
 * @property int $wrong_count
 * @property int $accuracy
 * @property int $xp_awarded
 * @property bool $is_perfect
 * @property bool $is_replay
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $completed_at
 * @property string|null $idempotency_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $exam_blueprint_id
 * @property numeric|null $net
 * @property numeric|null $estimated_score
 * @property-read Collection<int, SessionItem> $items
 * @property-read int|null $items_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereAccuracy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereConsumesHearts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereCorrectCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereEstimatedScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereExamBlueprintId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereIsPerfect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereIsReplay($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereNet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereTimeLimitSec($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereUnitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereUnitNodeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereWrongCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudySession whereXpAwarded($value)
 *
 * @mixin \Eloquent
 */
final class StudySession extends Model
{
    use HasUuids;

    /**
     * Yeni bir kolon eklendiğinde BURAYA da eklenmeli.
     *
     * Eksik kalırsa mass assignment onu SESSİZCE düşürür: kayıt oluşur,
     * alan null kalır, hata yoktur. exam_blueprint_id'nin unutulması tam
     * olarak buna yol açmıştı.
     */
    protected $fillable = [
        'uuid', 'user_id', 'unit_node_id', 'course_id', 'unit_id',
        'exam_blueprint_id', 'status',
        'consumes_hearts', 'time_limit_sec', 'correct_count', 'wrong_count',
        'accuracy', 'net', 'estimated_score', 'xp_awarded', 'is_perfect', 'is_replay',
        'started_at', 'expires_at', 'completed_at', 'idempotency_key',
    ];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'consumes_hearts' => 'boolean',
            'is_perfect' => 'boolean',
            'is_replay' => 'boolean',
            'started_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<SessionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SessionItem::class)->orderBy('position');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function answeredCount(): int
    {
        return $this->correct_count + $this->wrong_count;
    }
}
