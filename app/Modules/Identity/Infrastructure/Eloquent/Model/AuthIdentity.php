<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_user_id
 * @property string|null $email
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthIdentity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthIdentity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthIdentity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthIdentity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthIdentity whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthIdentity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthIdentity whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthIdentity whereProviderUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthIdentity whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthIdentity whereUserId($value)
 *
 * @mixin \Eloquent
 */
final class AuthIdentity extends Model
{
    protected $fillable = ['user_id', 'provider', 'provider_user_id', 'email'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
