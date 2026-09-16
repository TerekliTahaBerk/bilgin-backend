<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Infrastructure\Eloquent\Model;

use App\Shared\Domain\Enum\PublishStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * "TYT Genel Deneme · 120 soru · 165 dk · yanlış 1/4 götürür"
 *
 * @property int $id
 * @property int $exam_id
 * @property int|null $exam_section_id
 * @property int|null $exam_variant_id
 * @property string $code
 * @property string $name
 * @property int $duration_min
 * @property array<array-key, mixed> $scoring_rule
 * @property PublishStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Exam $exam
 * @property-read Collection<int, ExamBlueprintItem> $items
 * @property-read int|null $items_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprint newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprint newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprint query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprint whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprint whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprint whereDurationMin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprint whereExamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprint whereExamSectionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprint whereExamVariantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprint whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprint whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprint whereScoringRule($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprint whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExamBlueprint whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class ExamBlueprint extends Model
{
    protected $fillable = [
        'exam_id', 'exam_section_id', 'exam_variant_id', 'code', 'name',
        'duration_min', 'scoring_rule', 'status',
    ];

    protected function casts(): array
    {
        return ['scoring_rule' => 'array', 'status' => PublishStatus::class];
    }

    /** @return HasMany<ExamBlueprintItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ExamBlueprintItem::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function totalQuestions(): int
    {
        return (int) $this->items()->sum('question_count');
    }
}
