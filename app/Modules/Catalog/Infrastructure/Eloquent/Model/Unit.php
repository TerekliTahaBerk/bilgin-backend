<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Eloquent\Model;

use App\Shared\Domain\Enum\AccessLevel;
use App\Shared\Domain\Enum\PublishStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property string|null $description
 * @property int $sort_order
 * @property int|null $grade_level
 * @property string|null $difficulty_band
 * @property AccessLevel $access
 * @property int|null $estimated_minutes
 * @property PublishStatus $status
 * @property CarbonImmutable|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Course $course
 * @property-read Collection<int, UnitNode> $nodes
 * @property-read int|null $nodes_count
 * @property-read Collection<int, Topic> $topics
 * @property-read int|null $topics_count
 *
 * @method static Builder<static>|Unit newModelQuery()
 * @method static Builder<static>|Unit newQuery()
 * @method static Builder<static>|Unit published()
 * @method static Builder<static>|Unit query()
 * @method static Builder<static>|Unit whereAccess($value)
 * @method static Builder<static>|Unit whereCourseId($value)
 * @method static Builder<static>|Unit whereCreatedAt($value)
 * @method static Builder<static>|Unit whereDescription($value)
 * @method static Builder<static>|Unit whereDifficultyBand($value)
 * @method static Builder<static>|Unit whereEstimatedMinutes($value)
 * @method static Builder<static>|Unit whereGradeLevel($value)
 * @method static Builder<static>|Unit whereId($value)
 * @method static Builder<static>|Unit wherePublishedAt($value)
 * @method static Builder<static>|Unit whereSortOrder($value)
 * @method static Builder<static>|Unit whereStatus($value)
 * @method static Builder<static>|Unit whereTitle($value)
 * @method static Builder<static>|Unit whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class Unit extends Model
{
    protected $fillable = [
        'course_id', 'title', 'description', 'sort_order', 'grade_level',
        'difficulty_band', 'access', 'estimated_minutes', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'access' => AccessLevel::class,
            'status' => PublishStatus::class,
            'grade_level' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<UnitNode, $this> */
    public function nodes(): HasMany
    {
        return $this->hasMany(UnitNode::class)->orderBy('sort_order');
    }

    /** @return BelongsToMany<Topic, $this> */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'unit_topics')->withPivot('weight');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PublishStatus::Published);
    }
}
