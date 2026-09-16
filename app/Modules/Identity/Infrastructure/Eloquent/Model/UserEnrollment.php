<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent\Model;

use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamVariant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $exam_variant_id
 * @property bool $is_primary
 * @property CarbonImmutable $enrolled_at
 * @property CarbonImmutable|null $field_changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ExamVariant $examVariant
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEnrollment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEnrollment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEnrollment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEnrollment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEnrollment whereEnrolledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEnrollment whereExamVariantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEnrollment whereFieldChangedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEnrollment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEnrollment whereIsPrimary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEnrollment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserEnrollment whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class UserEnrollment extends Model
{
    protected $fillable = ['user_id', 'exam_variant_id', 'is_primary', 'enrolled_at', 'field_changed_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'enrolled_at' => 'immutable_datetime',
            'field_changed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ExamVariant, $this> */
    public function examVariant(): BelongsTo
    {
        return $this->belongsTo(ExamVariant::class);
    }
}
