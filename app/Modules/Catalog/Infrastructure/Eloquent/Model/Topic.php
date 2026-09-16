<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Eloquent\Model;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $subject_id
 * @property int|null $parent_id
 * @property string $code
 * @property string $name
 * @property int|null $grade_level
 * @property int|null $exam_weight
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Topic> $children
 * @property-read int|null $children_count
 * @property-read Collection<int, Exercise> $exercises
 * @property-read int|null $exercises_count
 * @property-read Topic|null $parent
 * @property-read Subject $subject
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Topic newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Topic newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Topic query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Topic whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Topic whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Topic whereExamWeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Topic whereGradeLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Topic whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Topic whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Topic whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Topic whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Topic whereSubjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Topic whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class Topic extends Model
{
    protected $fillable = ['subject_id', 'parent_id', 'code', 'name', 'grade_level', 'exam_weight', 'sort_order'];

    protected function casts(): array
    {
        return ['grade_level' => 'integer', 'exam_weight' => 'integer'];
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<Topic, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Topic, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<Exercise, $this> */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }
}
