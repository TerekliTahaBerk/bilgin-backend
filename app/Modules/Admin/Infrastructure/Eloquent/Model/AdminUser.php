<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent\Model;

use App\Modules\Admin\Domain\Enum\AdminRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $email
 * @property string $password
 * @property AdminRole $role
 * @property bool $is_active
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminUser whereUuid($value)
 *
 * @mixin \Eloquent
 */
final class AdminUser extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $fillable = ['uuid', 'name', 'email', 'password', 'role', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => AdminRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'immutable_datetime',
        ];
    }
}
