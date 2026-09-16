<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Infrastructure\Eloquent\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sınav oturumu — tasarımdaki TYT / AYT sekmesi.
 *
 * @property int $id
 * @property int $exam_id
 * @property string $code
 * @property string $name
 * @property int|null $duration_min
 * @property int|null $question_count
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Exam $exam
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSection query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSection whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSection whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSection whereDurationMin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSection whereExamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSection whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSection whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSection whereQuestionCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSection whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamSection whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class ExamSection extends Model
{
    protected $fillable = ['exam_id', 'code', 'name', 'duration_min', 'question_count', 'sort_order'];

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
