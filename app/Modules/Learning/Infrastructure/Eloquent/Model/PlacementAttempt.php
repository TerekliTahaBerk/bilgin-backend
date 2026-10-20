<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $exam_variant_id
 * @property string $status
 * @property array<int, array<string, mixed>> $questions
 * @property array<string, mixed>|null $result
 * @property int $question_count
 * @property int $correct_count
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlacementAttempt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlacementAttempt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlacementAttempt query()
 *
 * @mixin \Eloquent
 */
final class PlacementAttempt extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'result' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === 'active';
    }
}
