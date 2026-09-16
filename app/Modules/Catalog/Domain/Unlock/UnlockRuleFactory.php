<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Unlock;

use App\Modules\Catalog\Domain\Unlock\Rule\AllOf;
use App\Modules\Catalog\Domain\Unlock\Rule\AlwaysUnlocked;
use App\Modules\Catalog\Domain\Unlock\Rule\AnyOf;
use App\Modules\Catalog\Domain\Unlock\Rule\MinAccuracy;
use App\Modules\Catalog\Domain\Unlock\Rule\PreviousNodeCompleted;
use App\Modules\Catalog\Domain\Unlock\Rule\PreviousUnitCompleted;
use App\Modules\Catalog\Domain\Unlock\Rule\RequiresPremium;
use App\Modules\Catalog\Domain\Unlock\Rule\UnitStudyNodesCompleted;
use InvalidArgumentException;

/**
 * JSON kural ağacını çalışan nesnelere çevirir.
 *
 * Bu bilinçli olarak bir ifade dili (DSL) DEĞİLDİR: sabit şemalı, kapalı
 * uçlu bir yapıdır. Panelde form ile üretilir, elle yazılmaz. Bir DSL,
 * içerik ekibine kod yazdırmak anlamına gelirdi.
 */
final class UnlockRuleFactory
{
    /** @param  array<string, mixed>|null  $definition */
    public function make(?array $definition): UnlockRule
    {
        if ($definition === null || $definition === []) {
            return new AlwaysUnlocked;
        }

        $type = $definition['type'] ?? throw new InvalidArgumentException('unlock_rule.type eksik.');

        return match ($type) {
            'always' => new AlwaysUnlocked,
            'previous_node_completed' => new PreviousNodeCompleted,
            'previous_unit_completed' => new PreviousUnitCompleted,
            'unit_study_nodes_completed' => new UnitStudyNodesCompleted,
            'requires_premium' => new RequiresPremium,
            'min_accuracy' => new MinAccuracy((int) ($definition['value'] ?? 80)),
            'all_of' => new AllOf($this->makeChildren($definition)),
            'any_of' => new AnyOf($this->makeChildren($definition)),
            default => throw new InvalidArgumentException("Bilinmeyen unlock_rule tipi: {$type}"),
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return list<UnlockRule>
     */
    private function makeChildren(array $definition): array
    {
        $rules = $definition['rules'] ?? [];

        if (! is_array($rules) || $rules === []) {
            throw new InvalidArgumentException("'{$definition['type']}' en az bir alt kural gerektirir.");
        }

        return array_values(array_map($this->make(...), $rules));
    }
}
