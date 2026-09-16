<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Path;

/** Müfredat sırası. Öğrenci hakkında hiçbir şey bilinmediğinde varsayılan. */
final readonly class SequentialPath implements PathStrategy
{
    public function name(): string
    {
        return 'sequential';
    }

    /**
     * @param  list<UnitCard>  $units
     * @return list<int>
     */
    public function order(array $units, LearnerContext $learner): array
    {
        $sorted = $units;
        usort($sorted, static fn (UnitCard $a, UnitCard $b): int => $a->sortOrder <=> $b->sortOrder);

        return array_map(static fn (UnitCard $u): int => $u->id, $sorted);
    }
}
