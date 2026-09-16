<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $exercise_id
 * @property int $topic_id
 * @property int $study_session_id
 * @property bool $is_correct
 * @property float $partial_score
 * @property int $elapsed_ms
 * @property bool $is_suspicious
 * @property CarbonImmutable $answered_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerAttempt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerAttempt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerAttempt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerAttempt whereAnsweredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerAttempt whereElapsedMs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerAttempt whereExerciseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerAttempt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerAttempt whereIsCorrect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerAttempt whereIsSuspicious($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerAttempt wherePartialScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerAttempt whereStudySessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerAttempt whereTopicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AnswerAttempt whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class AnswerAttempt extends Model
{
    protected $table = 'answer_attempts';

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'partial_score' => 'float',
            'answered_at' => 'immutable_datetime',
        ];
    }
}
