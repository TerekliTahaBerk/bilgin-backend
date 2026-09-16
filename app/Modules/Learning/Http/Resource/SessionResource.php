<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Resource;

use App\Modules\Learning\Infrastructure\Eloquent\Model\SessionItem;
use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use App\Shared\Domain\Hearts\HeartBalance;
use App\Shared\Http\Resource\HeartResource;

/**
 * Çalışma ekranının başlangıç verisi.
 *
 * answer_key_snapshot BURADA YOK ve olmamalı. Doğru cevap yalnızca soru
 * cevaplandıktan sonra, AnswerResource ile gider.
 */
final readonly class SessionResource
{
    /** @return array<string, mixed> */
    public static function toArray(StudySession $session, HeartBalance $hearts): array
    {
        return array_filter([
            'session_id' => $session->uuid,
            // Bir oturum ya node'a ya blueprint'e aittir; istemci hangi
            // ekranda olduğunu buradan anlar.
            'kind' => $session->exam_blueprint_id !== null ? 'exam_simulation' : 'study',
            'node_id' => $session->unit_node_id,
            'blueprint_id' => $session->exam_blueprint_id,
            'consumes_hearts' => $session->consumes_hearts,
            'time_limit_sec' => $session->time_limit_sec,
            'expires_at' => $session->expires_at->format(DATE_ATOM),
            'hearts' => HeartResource::toArray($hearts),
            'items' => $session->items->map(self::item(...))->all(),
        ], static fn ($v): bool => $v !== null);
    }

    /** @return array<string, mixed> */
    private static function item(SessionItem $item): array
    {
        return [
            'position' => $item->position,
            'exercise_id' => $item->exercise_id,
            'type' => $item->type->value,
            'content' => $item->content_snapshot,
            'answered' => $item->isAnswered(),
        ];
    }
}
