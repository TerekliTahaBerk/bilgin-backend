<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Eloquent\Model;

use App\Modules\Catalog\Domain\Enum\CourseScope;
use App\Shared\Domain\Enum\PublishStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $subject_id
 * @property string $code
 * @property string $name
 * @property string|null $short_name
 * @property CourseScope $scope
 * @property string|null $icon
 * @property string|null $color
 * @property int|null $default_section_id
 * @property array<array-key, mixed>|null $grade_range
 * @property int|null $effective_from_year
 * @property PublishStatus $status
 * @property CarbonImmutable|null $published_at
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Subject $subject
 * @property-read Collection<int, Unit> $units
 * @property-read int|null $units_count
 *
 * @method static Builder<static>|Course newModelQuery()
 * @method static Builder<static>|Course newQuery()
 * @method static Builder<static>|Course published()
 * @method static Builder<static>|Course query()
 * @method static Builder<static>|Course whereCode($value)
 * @method static Builder<static>|Course whereColor($value)
 * @method static Builder<static>|Course whereCreatedAt($value)
 * @method static Builder<static>|Course whereDefaultSectionId($value)
 * @method static Builder<static>|Course whereEffectiveFromYear($value)
 * @method static Builder<static>|Course whereGradeRange($value)
 * @method static Builder<static>|Course whereIcon($value)
 * @method static Builder<static>|Course whereId($value)
 * @method static Builder<static>|Course whereName($value)
 * @method static Builder<static>|Course wherePublishedAt($value)
 * @method static Builder<static>|Course whereScope($value)
 * @method static Builder<static>|Course whereShortName($value)
 * @method static Builder<static>|Course whereSortOrder($value)
 * @method static Builder<static>|Course whereStatus($value)
 * @method static Builder<static>|Course whereSubjectId($value)
 * @method static Builder<static>|Course whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class Course extends Model
{
    protected $fillable = [
        'subject_id', 'code', 'name', 'short_name', 'scope', 'icon', 'color',
        'default_section_id', 'grade_range', 'effective_from_year', 'status',
        'published_at', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'scope' => CourseScope::class,
            'status' => PublishStatus::class,
            'grade_range' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return HasMany<Unit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
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
