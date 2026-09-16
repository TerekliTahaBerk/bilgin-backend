<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Eloquent\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property array<array-key, mixed> $nodes
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitTemplate whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitTemplate whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitTemplate whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitTemplate whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitTemplate whereNodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UnitTemplate whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class UnitTemplate extends Model
{
    protected $fillable = ['code', 'name', 'description', 'nodes', 'is_default'];

    protected function casts(): array
    {
        return ['nodes' => 'array', 'is_default' => 'boolean'];
    }
}
