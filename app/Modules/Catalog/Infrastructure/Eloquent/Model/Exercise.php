<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Eloquent\Model;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Shared\Domain\Enum\PublishStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $topic_id
 * @property int|null $owner_course_id
 * @property int|null $owner_unit_id
 * @property ExerciseType $type
 * @property array<array-key, mixed> $content
 * @property array<array-key, mixed> $answer_key
 * @property string|null $explanation
 * @property int $difficulty
 * @property array<array-key, mixed> $applicable_scopes
 * @property array<array-key, mixed>|null $media
 * @property PublishStatus $status
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Topic $topic
 *
 * @method static Builder<static>|Exercise forScope(string $scope)
 * @method static Builder<static>|Exercise newModelQuery()
 * @method static Builder<static>|Exercise newQuery()
 * @method static Builder<static>|Exercise published()
 * @method static Builder<static>|Exercise query()
 * @method static Builder<static>|Exercise whereAnswerKey($value)
 * @method static Builder<static>|Exercise whereApplicableScopes($value)
 * @method static Builder<static>|Exercise whereContent($value)
 * @method static Builder<static>|Exercise whereCreatedAt($value)
 * @method static Builder<static>|Exercise whereDifficulty($value)
 * @method static Builder<static>|Exercise whereExplanation($value)
 * @method static Builder<static>|Exercise whereId($value)
 * @method static Builder<static>|Exercise whereMedia($value)
 * @method static Builder<static>|Exercise whereOwnerCourseId($value)
 * @method static Builder<static>|Exercise whereOwnerUnitId($value)
 * @method static Builder<static>|Exercise whereStatus($value)
 * @method static Builder<static>|Exercise whereTopicId($value)
 * @method static Builder<static>|Exercise whereType($value)
 * @method static Builder<static>|Exercise whereUpdatedAt($value)
 * @method static Builder<static>|Exercise whereUuid($value)
 * @method static Builder<static>|Exercise whereVersion($value)
 *
 * @mixin \Eloquent
 */
final class Exercise extends Model
{
    use HasUuids;

    protected $fillable = [
        // uuid AÇIKÇA listeleniyor: içerik içe aktarma onu parmak izinden
        // türetip elle veriyor (idempotency buna bağlı). HasUuids'in otomatik
        // ürettiğine güvenmek, aynı paketin ikinci kez aktarılmasında soruları
        // çoğaltırdı.
        'uuid',
        'topic_id', 'owner_course_id', 'owner_unit_id', 'type', 'content',
        'answer_key', 'explanation', 'difficulty', 'applicable_scopes', 'media',
        'status', 'version',
    ];

    /**
     * answer_key asla serileştirilmez. Bu, "unuttum" ihtimalini ortadan kaldıran
     * son savunma hattıdır; asıl koruma API Resource'larının onu hiç okumamasıdır.
     */
    protected $hidden = ['answer_key'];

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'type' => ExerciseType::class,
            'status' => PublishStatus::class,
            'content' => 'array',
            'answer_key' => 'array',
            'applicable_scopes' => 'array',
            'media' => 'array',
            'difficulty' => 'integer',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Topic, $this> */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PublishStatus::Published);
    }

    /** applicable_scopes JSON dizisinde verilen kapsamı içerenler. */
    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForScope(Builder $query, string $scope): Builder
    {
        return $query->whereJsonContains('applicable_scopes', $scope);
    }
}
