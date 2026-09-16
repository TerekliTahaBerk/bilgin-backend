<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Infrastructure\Eloquent\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property int $amount
 * @property string $source_type
 * @property int $source_id
 * @property int|null $course_id
 * @property bool $counts_for_league
 * @property array<array-key, mixed>|null $breakdown
 * @property CarbonImmutable $awarded_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedgerEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedgerEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedgerEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedgerEntry whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedgerEntry whereAwardedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedgerEntry whereBreakdown($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedgerEntry whereCountsForLeague($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedgerEntry whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedgerEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedgerEntry whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedgerEntry whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedgerEntry whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class XpLedgerEntry extends Model
{
    protected $table = 'xp_ledger';

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'breakdown' => 'array',
            'counts_for_league' => 'boolean',
            'awarded_at' => 'immutable_datetime',
        ];
    }
}
