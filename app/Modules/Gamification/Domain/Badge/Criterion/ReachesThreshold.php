<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Domain\Badge\Criterion;

use App\Modules\Gamification\Domain\Badge\BadgeCriterion;
use App\Modules\Gamification\Domain\Badge\BadgeEvaluation;
use App\Modules\Gamification\Domain\Badge\BadgeSnapshot;

/**
 * "Şu metrik şu değere ulaşsın" — rozetlerin çoğu bu.
 *
 * İlk Çalışma, 7 Gün Seri, 100 Doğru, 5.000 XP, İlk Ünite, Perfect...
 * hepsi aynı sınıfın farklı yapılandırması. Her biri için ayrı sınıf
 * yazmak, on kere kopyalanmış bir karşılaştırma demek olurdu.
 */
final readonly class ReachesThreshold implements BadgeCriterion
{
    public function __construct(
        private string $metric,
        private int $target,
    ) {}

    public function evaluate(BadgeSnapshot $snapshot): BadgeEvaluation
    {
        $current = $snapshot->valueFor($this->metric);

        return new BadgeEvaluation(
            earned: $current >= $this->target,
            current: min($current, $this->target),
            target: $this->target,
        );
    }
}
