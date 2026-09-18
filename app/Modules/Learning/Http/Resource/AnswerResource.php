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
        // Denemede sonuç bilgisi HİÇ gönderilmiyor. İstemcinin göstermemesi
        // yetmez: yanıtta duran cevap anahtarı, araya giren biri tarafından
        // okunabilir. Sınav bitince `complete` zaten net ve puanı döndürüyor.
        $reveals = $outcome->revealsAnswer;

        return array_filter([
            'is_correct' => $reveals ? $outcome->isCorrect : null,
            'partial_score' => $reveals ? round($outcome->partialScore, 3) : null,
            'correct_answer' => $reveals ? $outcome->correctAnswer : null,
            'explanation' => $reveals ? $outcome->explanation : null,
            'hearts' => $outcome->hearts !== null ? HeartResource::toArray($outcome->hearts) : null,
            'hearts_depleted' => $outcome->heartsDepleted() ?: null,
            'progress' => ['answered' => $outcome->answered, 'total' => $outcome->total],
            'suspicious' => $outcome->suspicious ?: null,
            'idempotent_replay' => $outcome->idempotentReplay ?: null,
        ], static fn ($v): bool => $v !== null);
    }
}
