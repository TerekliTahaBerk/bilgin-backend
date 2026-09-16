<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Infrastructure\Eloquent\Model;

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Curriculum\Domain\Enum\FieldCode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $exam_id
 * @property string $code
 * @property string $name
 * @property FieldCode $field_code
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Course> $courses
 * @property-read int|null $courses_count
 * @property-read Exam $exam
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamVariant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamVariant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamVariant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamVariant whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamVariant whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamVariant whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamVariant whereExamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamVariant whereFieldCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamVariant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamVariant whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamVariant whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamVariant whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamVariant whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class ExamVariant extends Model
{
    protected $fillable = ['exam_id', 'code', 'name', 'field_code', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['field_code' => FieldCode::class, 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Curriculum → Catalog yönünde tek yönlü referans. Bu, modül sınırının
     * izin verilen tek geçişidir: Curriculum course'lara referans verir,
     * Catalog Curriculum'ü hiç tanımaz.
     */
    /** @return BelongsToMany<Course, $this> */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'exam_variant_courses')
            ->withPivot(['exam_section_id', 'sort_order', 'is_required', 'access', 'exam_weight', 'placeholder_label'])
            ->orderBy('exam_variant_courses.sort_order');
    }
}
