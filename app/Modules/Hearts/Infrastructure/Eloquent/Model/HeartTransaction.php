<?php

declare(strict_types=1);

namespace App\Modules\Hearts\Infrastructure\Eloquent\Model;

use App\Modules\Hearts\Domain\Enum\HeartReason;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $delta
 * @property HeartReason $reason
 * @property int $balance_after
 * @property string|null $reference_key
 * @property CarbonImmutable $created_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HeartTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HeartTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HeartTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HeartTransaction whereBalanceAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HeartTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HeartTransaction whereDelta($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HeartTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HeartTransaction whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HeartTransaction whereReferenceKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|HeartTransaction whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class HeartTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'delta', 'reason', 'balance_after',
        'reference_key', 'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['reason' => HeartReason::class, 'created_at' => 'immutable_datetime'];
    }
}
