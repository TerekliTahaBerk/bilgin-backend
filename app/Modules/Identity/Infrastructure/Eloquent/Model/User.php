<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent\Model;

use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamVariant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $name
 * @property string|null $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $password
 * @property string|null $avatar_key
 * @property bool $is_guest
 * @property string $timezone
 * @property string $locale
 * @property int|null $birth_year
 * @property CarbonImmutable|null $parental_consent_at
 * @property CarbonImmutable|null $last_active_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Device> $devices
 * @property-read int|null $devices_count
 * @property-read Collection<int, UserEnrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read Collection<int, AuthIdentity> $identities
 * @property-read int|null $identities_count
 * @property-read UserProfile|null $profile
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatarKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBirthYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsGuest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastActiveAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereParentalConsentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTimezone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class User extends Authenticatable
{
    use HasApiTokens, HasUuids, SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'email', 'email_verified_at', 'password', 'avatar_key',
        'is_guest', 'timezone', 'locale', 'birth_year', 'parental_consent_at',
        'last_active_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Veritabanı varsayılanları modele YANSIMAZ: create() sonrası okunan
     * özellik null gelir ve istemciye null gider. Varsayılanları burada
     * tanımlamak, hangi yoldan yaratılırsa yaratılsın doğru değeri garanti eder.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_guest' => true,
        'timezone' => 'Europe/Istanbul',
        'locale' => 'tr',
    ];

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
            'is_guest' => 'boolean',
            'email_verified_at' => 'immutable_datetime',
            'parental_consent_at' => 'immutable_datetime',
            'last_active_at' => 'immutable_datetime',
        ];
    }

    /** @return HasOne<UserProfile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /** @return HasMany<UserEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(UserEnrollment::class);
    }

    /** @return HasMany<AuthIdentity, $this> */
    public function identities(): HasMany
    {
        return $this->hasMany(AuthIdentity::class);
    }

    /** @return HasMany<Device, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Öğrencinin birincil sınav varyantı — ders listesi ve yol bundan türer.
     */
    public function primaryVariant(): ?ExamVariant
    {
        return $this->enrollments()
            ->where('is_primary', true)
            ->with('examVariant')
            ->first()?->examVariant;
    }

    /**
     * 18 yaş altı mı? Bilinmiyorsa TRUE döner — reklam kişiselleştirme ve
     * veri işleme kararlarında güvenli taraf budur (KVKK).
     */
    public function isMinor(?int $currentYear = null): bool
    {
        if ($this->birth_year === null) {
            return true;
        }

        return ($currentYear ?? (int) date('Y')) - $this->birth_year < 18;
    }
}
