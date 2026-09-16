<?php

declare(strict_types=1);

namespace App\Modules\League\Infrastructure\Eloquent\Model;

use App\Modules\League\Domain\Enum\LeagueTier;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property LeagueTier $tier
 * @property Carbon $week_start
 * @property int $capacity
 * @property int $member_count
 * @property string $status
 * @property CarbonImmutable|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, LeagueMembership> $memberships
 * @property-read int|null $memberships_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereClosedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereMemberCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereTier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereWeekStart($value)
 *
 * @mixin \Eloquent
 */
final class League extends Model
{
    protected $fillable = ['tier', 'week_start', 'capacity', 'member_count', 'status', 'closed_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tier' => LeagueTier::class,
            'week_start' => 'date',
            'closed_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<LeagueMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(LeagueMembership::class);
    }

    public function isFull(): bool
    {
        return $this->member_count >= $this->capacity;
    }
}
