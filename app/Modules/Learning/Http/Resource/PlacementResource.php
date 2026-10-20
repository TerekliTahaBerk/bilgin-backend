<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Resource;

use App\Modules\Learning\Infrastructure\Eloquent\Model\PlacementAttempt;

/**
 * Seviye testinin istemciye giden hâli.
 *
 * `answer_key` BURADA YOK ve olmamalı: cevap anahtarı yalnızca sunucuda
 * yaşıyor. Testin tamamı tek istekte indiği için bu, akılda tutulması
 * gereken bir ayrıntı değil — gözden kaçarsa testin tamamı anlamsızlaşır.
 */
final readonly class PlacementResource
{
    /** @return array<string, mixed> */
    public static function toArray(PlacementAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'question_count' => $attempt->question_count,
            'questions' => array_map(
                static fn (array $q, int $i): array => [
                    'position' => $i + 1,
                    'exercise_id' => $q['exercise_id'],
                    'course_name' => $q['course_name'] ?? null,
                    'type' => $q['type'],
                    'content' => $q['content'],
                ],
                $attempt->questions,
                array_keys($attempt->questions),
            ),
        ];
    }
}
