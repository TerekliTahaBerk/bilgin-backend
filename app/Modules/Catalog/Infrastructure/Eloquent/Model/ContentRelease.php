<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Eloquent\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $version
 * @property string $manifest_hash
 * @property string|null $changelog
 * @property string|null $published_by
 * @property CarbonImmutable $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentRelease newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentRelease newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentRelease query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentRelease whereChangelog($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentRelease whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentRelease whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentRelease whereManifestHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentRelease wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentRelease wherePublishedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentRelease whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContentRelease whereVersion($value)
 *
 * @mixin \Eloquent
 */
final class ContentRelease extends Model
{
    protected $fillable = ['version', 'manifest_hash', 'changelog', 'published_by', 'published_at'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'published_at' => 'immutable_datetime'];
    }
}
