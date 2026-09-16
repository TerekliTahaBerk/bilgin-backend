<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Eloquent\Model;

use App\Modules\Catalog\Domain\Enum\DifficultyLevel;
use App\Modules\Catalog\Domain\Enum\NodeType;
use App\Shared\Domain\Enum\AccessLevel;
use App\Shared\Domain\Enum\PublishStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $unit_id
 * @property string $title
 * @property NodeType $node_type
 * @property DifficultyLevel $difficulty
 * @property int $sort_order
 * @property int $exercise_count
 * @property int|null $time_limit_sec
 * @property bool $consumes_hearts
 * @property int $xp_reward
 * @property AccessLevel $access
 * @property array<array-key, mixed> $selection_rule
 * @property array<array-key, mixed>|null $unlock_rule
 * @property string|null $preview_label
 * @property PublishStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit $unit
 *
 * @method static Builder<static>|UnitNode newModelQuery()
 * @method static Builder<static>|UnitNode newQuery()
 * @method static Builder<static>|UnitNode published()
 * @method static Builder<static>|UnitNode query()
 * @method static Builder<static>|UnitNode whereAccess($value)
 * @method static Builder<static>|UnitNode whereConsumesHearts($value)
 * @method static Builder<static>|UnitNode whereCreatedAt($value)
 * @method static Builder<static>|UnitNode whereDifficulty($value)
 * @method static Builder<static>|UnitNode whereExerciseCount($value)
 * @method static Builder<static>|UnitNode whereId($value)
 * @method static Builder<static>|UnitNode whereNodeType($value)
 * @method static Builder<static>|UnitNode wherePreviewLabel($value)
 * @method static Builder<static>|UnitNode whereSelectionRule($value)
 * @method static Builder<static>|UnitNode whereSortOrder($value)
 * @method static Builder<static>|UnitNode whereStatus($value)
 * @method static Builder<static>|UnitNode whereTimeLimitSec($value)
 * @method static Builder<static>|UnitNode whereTitle($value)
 * @method static Builder<static>|UnitNode whereUnitId($value)
 * @method static Builder<static>|UnitNode whereUnlockRule($value)
 * @method static Builder<static>|UnitNode whereUpdatedAt($value)
 * @method static Builder<static>|UnitNode whereXpReward($value)
 *
 * @mixin \Eloquent
 */
final class UnitNode extends Model
{
    protected $fillable = [
        'unit_id', 'title', 'node_type', 'difficulty', 'sort_order', 'exercise_count',
        'time_limit_sec', 'consumes_hearts', 'xp_reward', 'access', 'selection_rule',
        'unlock_rule', 'preview_label', 'status',
    ];

    protected function casts(): array
    {
        return [
            'node_type' => NodeType::class,
            'difficulty' => DifficultyLevel::class,
            'access' => AccessLevel::class,
            'status' => PublishStatus::class,
            'selection_rule' => 'array',
            'unlock_rule' => 'array',
            'consumes_hearts' => 'boolean',
        ];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
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
