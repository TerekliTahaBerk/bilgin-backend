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
            'context' => self::context($session),
            'items' => $session->items->map(self::item(...))->all(),
        ], static fn ($v): bool => $v !== null);
    }

    /**
     * "Hangi turdayım" künyesi — tasarımdaki `TARİH · TUR 2` çipi.
     *
     * Arayüz bu üç parçayı kendi birleştiriyor; burada hazır bir dize
     * üretmiyoruz. Sebep: aynı veriyi farklı ekranlar farklı biçimde
     * gösteriyor (çip kısa, erişilebilirlik etiketi uzun) ve sunucunun
     * biçimlendirmeye karar vermesi, arayüzü sunucu sürümüne bağlardı.
     *
     * Denemede null: sınav provasının bir adımı yok.
     *
     * @return array<string, mixed>|null
     */
    private static function context(StudySession $session): ?array
    {
        // Önce sütuna bakılıyor, ilişkiye değil: denemede `unit_node_id`
        // zaten null ve ilişkiyi okumak boşuna bir sorgu açardı.
        if ($session->unit_node_id === null) {
            return null;
        }

        $node = $session->node;

        if ($node === null) {
            return null;
        }

        return [
            'node_title' => $node->title,
            'unit_title' => $node->unit->title,
            'course_name' => $node->unit->course->short_name ?? $node->unit->course->name,
            // Adımın ünite içindeki sırası — "TUR 2"nin kaynağı.
            'position' => $node->sort_order,
        ];
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
