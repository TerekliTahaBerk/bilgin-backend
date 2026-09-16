<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Selection\Strategy;

use App\Modules\Catalog\Domain\Contract\ExercisePool;
use App\Modules\Catalog\Domain\Enum\SelectionMode;
use App\Modules\Catalog\Domain\Selection\ExerciseSelector;
use App\Modules\Catalog\Domain\Selection\SelectionContext;
use App\Modules\Catalog\Domain\Selection\SelectionResult;
use App\Modules\Catalog\Domain\Selection\SelectionRule;

/**
 * Elle seçilmiş, sırası önemli soru listesi.
 *
 * Öğretici sıralamanın kritik olduğu giriş node'ları için: havuzun
 * rastgeleliği her zaman istenmez.
 */
final readonly class FixedListSelector implements ExerciseSelector
{
    public function __construct(private ExercisePool $pool) {}

    public function mode(): SelectionMode
    {
        return SelectionMode::Fixed;
    }

    public function select(SelectionRule $rule, SelectionContext $context): SelectionResult
    {
        $found = $this->pool->findPublishedByIds($rule->exerciseIds);

        // Kuraldaki sırayı koru — yayınlanmamış olanlar listeden düşer.
        $byId = [];
        foreach ($found as $ref) {
            $byId[$ref->id] = $ref;
        }

        $ordered = [];
        foreach ($rule->exerciseIds as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        $requested = $rule->count > 0 ? $rule->count : count($rule->exerciseIds);

        return new SelectionResult($ordered, $requested);
    }
}
