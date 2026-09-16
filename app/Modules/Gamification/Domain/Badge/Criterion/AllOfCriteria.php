<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Domain\Badge\Criterion;

use App\Modules\Gamification\Domain\Badge\BadgeCriterion;
use App\Modules\Gamification\Domain\Badge\BadgeEvaluation;
use App\Modules\Gamification\Domain\Badge\BadgeSnapshot;

/** Birden fazla şartın birlikte sağlanması gereken rozetler. */
final readonly class AllOfCriteria implements BadgeCriterion
{
    /** @param  list<BadgeCriterion>  $criteria */
    public function __construct(private array $criteria) {}

    public function evaluate(BadgeSnapshot $snapshot): BadgeEvaluation
    {
        $earned = true;
        $slowest = null;

        foreach ($this->criteria as $criterion) {
            $result = $criterion->evaluate($snapshot);
            $earned = $earned && $result->earned;

            // İlerleme çubuğu EN GERİDEKİ şartı gösterir: rozetin ne kadar
            // yakın olduğunu belirleyen o.
            if ($slowest === null || $result->progress() < $slowest->progress()) {
                $slowest = $result;
            }
        }

        // Kriter listesi boşsa rozet kazanılamaz; fabrika bunu zaten
        // engelliyor ama burada da savunmalı davranılıyor.
        if ($slowest === null) {
            return new BadgeEvaluation(earned: false, current: 0, target: 1);
        }

        return new BadgeEvaluation(
            earned: $earned,
            current: $slowest->current,
            target: $slowest->target,
        );
    }
}
