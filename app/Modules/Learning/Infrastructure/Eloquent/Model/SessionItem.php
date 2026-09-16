<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent\Model;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $study_session_id
 * @property int $exercise_id
 * @property int $topic_id
 * @property int $position
 * @property ExerciseType $type
 * @property int $exercise_version
 * @property array<array-key, mixed> $content_snapshot
 * @property array<array-key, mixed> $answer_key_snapshot
 * @property string|null $explanation_snapshot
 * @property CarbonImmutable|null $answered_at
 * @property bool|null $is_correct
 * @property float|null $partial_score
 * @property int|null $elapsed_ms
 * @property bool $is_suspicious
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StudySession $session
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem whereAnswerKeySnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem whereAnsweredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem whereContentSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem whereElapsedMs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem whereExerciseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem whereExerciseVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem whereExplanationSnapshot($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem whereIsCorrect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem whereIsSuspicious($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem wherePartialScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem whereStudySessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem whereTopicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SessionItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class SessionItem extends Model
{
    protected $fillable = [
        'study_session_id', 'exercise_id', 'topic_id', 'position', 'type',
        'exercise_version', 'content_snapshot', 'answer_key_snapshot',
        'explanation_snapshot', 'answered_at', 'is_correct', 'partial_score',
        'elapsed_ms', 'is_suspicious',
    ];

    /**
     * Cevap anahtarı hiçbir serileştirmede yer almaz. API Resource'ları zaten
     * okumaz; bu, unutulma ihtimaline karşı son savunma hattıdır.
     *
     * @var list<string>
     */
    protected $hidden = ['answer_key_snapshot'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ExerciseType::class,
            'content_snapshot' => 'array',
            'answer_key_snapshot' => 'array',
            'answered_at' => 'immutable_datetime',
            'is_correct' => 'boolean',
            'partial_score' => 'float',
            'is_suspicious' => 'boolean',
        ];
    }

    /** @return BelongsTo<StudySession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(StudySession::class, 'study_session_id');
    }

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }
}
