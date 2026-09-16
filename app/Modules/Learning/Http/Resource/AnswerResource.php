<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Resource;

use App\Modules\Learning\Application\UseCase\AnswerOutcome;
use App\Shared\Http\Resource\HeartResource;

final readonly class AnswerResource
{
    /** @return array<string, mixed> */
    public static function toArray(AnswerOutcome $outcome): array
    {
        return array_filter([
            'is_correct' => $outcome->isCorrect,
            'partial_score' => round($outcome->partialScore, 3),
            // Doğru cevap ancak cevap verildikten SONRA gösterilir.
            'correct_answer' => $outcome->correctAnswer,
            'explanation' => $outcome->explanation,
            'hearts' => $outcome->hearts !== null ? HeartResource::toArray($outcome->hearts) : null,
            'hearts_depleted' => $outcome->heartsDepleted() ?: null,
            'progress' => ['answered' => $outcome->answered, 'total' => $outcome->total],
            'suspicious' => $outcome->suspicious ?: null,
            'idempotent_replay' => $outcome->idempotentReplay ?: null,
        ], static fn ($v): bool => $v !== null);
    }
}
