<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent\Model;

use App\Modules\Identity\Domain\Enum\DailyGoal;
use App\Modules\Identity\Domain\Enum\Grade;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Grade|null $grade
 * @property int|null $target_exam_year
 * @property int|null $target_exam_month
 * @property DailyGoal $daily_goal_rounds
 * @property bool $reminder_enabled
 * @property string|null $reminder_time
 * @property string|null $acquisition_source
 * @property CarbonImmutable|null $placement_completed_at
 * @property CarbonImmutable|null $onboarding_completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereAcquisitionSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereDailyGoalRounds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereGrade($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereOnboardingCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile wherePlacementCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereReminderEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereReminderTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereTargetExamMonth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereTargetExamYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserProfile whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class UserProfile extends Model
{
    protected $fillable = [
        'user_id', 'grade', 'target_exam_year', 'target_exam_month',
        'daily_goal_rounds', 'reminder_enabled', 'reminder_time',
        'acquisition_source', 'placement_completed_at', 'onboarding_completed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'grade' => Grade::class,
            'daily_goal_rounds' => DailyGoal::class,
            'reminder_enabled' => 'boolean',
            'placement_completed_at' => 'immutable_datetime',
            'onboarding_completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
