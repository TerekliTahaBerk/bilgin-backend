<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Domain\Badge;

/**
 * Rozet kriteri.
 *
 * Yeni rozet türü eklemek = yeni sınıf + fabrikada bir satır. Mevcut
 * hiçbir kriter açılmaz (OCP) — aynı desen kilit kuralları ve soru
 * seçicilerinde de kullanılıyor.
 */
interface BadgeCriterion
{
    public function evaluate(BadgeSnapshot $snapshot): BadgeEvaluation;
}
