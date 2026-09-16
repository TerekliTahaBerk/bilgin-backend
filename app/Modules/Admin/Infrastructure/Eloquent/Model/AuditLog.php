<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $admin_user_id
 * @property string $action
 * @property string $entity_type
 * @property int|null $entity_id
 * @property array<array-key, mixed>|null $before
 * @property array<array-key, mixed>|null $after
 * @property string|null $ip
 * @property CarbonImmutable $created_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereAdminUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereBefore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereEntityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereIp($value)
 *
 * @mixin \Eloquent
 */
final class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
