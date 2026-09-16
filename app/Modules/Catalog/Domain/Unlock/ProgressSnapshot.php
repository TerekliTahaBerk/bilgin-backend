<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Unlock;

/**
 * Kullanıcının o andaki ilerlemesinin salt-okunur görüntüsü.
 *
 * Kilit kuralları veritabanına değil bu nesneye bakar; böylece kural motoru
 * DB'siz test edilir ve N+1 sorgu üretmesi imkânsızdır (tüm veri tek seferde
 * yüklenir).
 */
final readonly class ProgressSnapshot
{
    /**
     * @param  array<int, bool>  $completedNodes  node id → tamamlandı mı
     * @param  array<int, int>  $nodeAccuracy  node id → en iyi doğruluk (0–100)
     * @param  array<int, bool>  $completedUnits  unit id → tamamlandı mı
     */
    public function __construct(
        private array $completedNodes = [],
        private array $nodeAccuracy = [],
        private array $completedUnits = [],
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    public function hasCompletedNode(?int $nodeId): bool
    {
        return $nodeId !== null && ($this->completedNodes[$nodeId] ?? false);
    }

    public function hasCompletedUnit(?int $unitId): bool
    {
        return $unitId !== null && ($this->completedUnits[$unitId] ?? false);
    }

    public function accuracyFor(?int $nodeId): int
    {
        return $nodeId === null ? 0 : ($this->nodeAccuracy[$nodeId] ?? 0);
    }

    /** @param  list<int>  $nodeIds */
    public function hasCompletedAll(array $nodeIds): bool
    {
        foreach ($nodeIds as $id) {
            if (! $this->hasCompletedNode($id)) {
                return false;
            }
        }

        return true;
    }
}
