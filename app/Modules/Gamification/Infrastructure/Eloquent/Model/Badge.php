<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Infrastructure\Eloquent\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $description
 * @property string|null $icon
 * @property array<array-key, mixed> $criteria
 * @property string $tier
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Badge newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Badge newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Badge query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Badge whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Badge whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Badge whereCriteria($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Badge whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Badge whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Badge whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Badge whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Badge whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Badge whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Badge whereTier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Badge whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class Badge extends Model
{
    protected $fillable = ['code', 'name', 'description', 'icon', 'criteria', 'tier', 'sort_order', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['criteria' => 'array', 'is_active' => 'boolean'];
    }
}
