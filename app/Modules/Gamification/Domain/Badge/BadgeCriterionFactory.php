<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Domain\Badge;

use App\Modules\Gamification\Domain\Badge\Criterion\AllOfCriteria;
use App\Modules\Gamification\Domain\Badge\Criterion\ReachesThreshold;
use InvalidArgumentException;

/**
 * badges.criteria JSON'unu çalışan kritere çevirir.
 *
 * Kilit kuralları ve soru seçimiyle aynı desen: sabit şemalı JSON, DSL değil.
 * Yeni rozet eklemek panelden bir kayıt; kod deploy'u gerekmez.
 */
final class BadgeCriterionFactory
{
    /** @param  array<string, mixed>  $definition */
    public function make(array $definition): BadgeCriterion
    {
        $type = $definition['type'] ?? throw new InvalidArgumentException('criteria.type eksik.');

        return match ($type) {
            'threshold' => new ReachesThreshold(
                metric: (string) ($definition['metric'] ?? throw new InvalidArgumentException('criteria.metric eksik.')),
                target: (int) ($definition['target'] ?? 1),
            ),
            'all_of' => new AllOfCriteria($this->makeChildren($definition)),
            default => throw new InvalidArgumentException("Bilinmeyen rozet kriteri: {$type}"),
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<BadgeCriterion>
     */
    private function makeChildren(array $definition): array
    {
        $rules = $definition['criteria'] ?? [];

        if (! is_array($rules) || $rules === []) {
            throw new InvalidArgumentException("'all_of' en az bir alt kriter gerektirir.");
        }

        return array_values(array_map($this->make(...), $rules));
    }
}
