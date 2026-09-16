<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Resource;

use App\Modules\Learning\Application\UseCase\SessionSummary;
use App\Shared\Http\Resource\HeartResource;

/**
 * Sonuç ekranının tamamı — tek yanıt.
 *
 * XP dökümü, level çubuğu, seri animasyonu, ünite yüzdesi ve "yeni node
 * açıldı" rozetinin hepsi burada. İstemcinin ek istek atmasına gerek yok;
 * kutlama animasyonu ağ gecikmesine takılmaz.
 */
final readonly class SessionSummaryResource
{
    /** @return array<string, mixed> */
    public static function toArray(SessionSummary $summary): array
    {
        $xp = $summary->xp;

        $payload = [
            'score' => [
                'correct' => $summary->correct,
                'total' => $summary->total,
                'accuracy' => $summary->accuracy,
                'is_perfect' => $summary->isPerfect,
                'passed' => $summary->meetsThreshold,
            ],
            'xp' => [
                'total' => $xp->award->total,
                'breakdown' => $xp->award->breakdown,
            ],
            'level' => [
                'before' => $xp->levelBefore,
                'after' => $xp->levelAfter,
                'leveled_up' => $xp->leveledUp(),
                'total_xp' => $xp->totalXp,
                'next_level_xp' => $xp->nextLevelXp,
            ],
            'streak' => [
                'days' => $xp->streak->current,
                'longest' => $xp->streak->longest,
                'extended_today' => $xp->streak->extendedToday,
                'milestone' => $xp->streak->milestone,
            ],
            'unlocked_nodes' => $summary->progress->unlockedNodeIds,
            'hearts' => HeartResource::toArray($summary->hearts),
            // Kutlama aynı yanıtta: rozet için ayrı istek atmak,
            // animasyonu ağ gecikmesine bağlardı.
            'badges_earned' => array_map(static fn ($badge): array => [
                'code' => $badge->code,
                'name' => $badge->name,
                'icon' => $badge->icon,
                'tier' => $badge->tier,
            ], $summary->newBadges),
        ];

        // Deneme bir üniteye ait değil; ünite bloğu yalnızca çalışma turunda.
        if ($summary->session->unit_id !== null) {
            $payload['unit'] = [
                'id' => $summary->session->unit_id,
                'completion_percent' => $summary->progress->unitCompletionPercent,
                'completed' => $summary->progress->unitCompleted,
            ];
        }

        if ($summary->examScore !== null) {
            // Deneme sonucu: net ve TAHMİNİ puan. Gerçek ÖSYM formülü yıllık
            // katsayılara bağlıdır; istemci bunu "tahmin" olarak etiketler.
            $payload['exam'] = [
                'correct' => $summary->examScore->correct,
                'wrong' => $summary->examScore->wrong,
                'blank' => $summary->examScore->blank,
                'net' => $summary->examScore->net,
                'estimated_score' => $summary->examScore->estimatedScore,
            ];
        }

        return $payload;
    }
}
