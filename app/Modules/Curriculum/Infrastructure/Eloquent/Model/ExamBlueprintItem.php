<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Infrastructure\Eloquent\Model;

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $exam_blueprint_id
 * @property int|null $course_id
 * @property int|null $topic_id
 * @property int $question_count
 * @property array<array-key, mixed>|null $difficulty_distribution
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ExamBlueprint $blueprint
 * @property-read Course|null $course
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprintItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprintItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprintItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprintItem whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprintItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprintItem whereDifficultyDistribution($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprintItem whereExamBlueprintId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprintItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprintItem whereQuestionCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprintItem whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprintItem whereTopicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprintItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class ExamBlueprintItem extends Model
{
    protected $fillable = [
        'exam_blueprint_id', 'course_id', 'topic_id', 'question_count',
        'difficulty_distribution', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['difficulty_distribution' => 'array', 'question_count' => 'integer'];
    }

    /** @return BelongsTo<ExamBlueprint, $this> */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(ExamBlueprint::class, 'exam_blueprint_id');
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
