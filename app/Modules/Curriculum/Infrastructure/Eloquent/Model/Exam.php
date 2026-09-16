<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Infrastructure\Eloquent\Model;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ExamSection> $sections
 * @property-read int|null $sections_count
 * @property-read Collection<int, ExamVariant> $variants
 * @property-read int|null $variants_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Exam whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class Exam extends Model
{
    protected $fillable = ['code', 'name', 'icon', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<ExamSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class)->orderBy('sort_order');
    }

    /** @return HasMany<ExamVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ExamVariant::class)->orderBy('sort_order');
    }
}
