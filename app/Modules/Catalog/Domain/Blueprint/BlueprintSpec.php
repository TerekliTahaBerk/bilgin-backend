<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Blueprint;

/**
 * Deneme kompozisyonu — sağlayıcı modülden bağımsız okuma modeli.
 *
 * Blueprint kayıtları Curriculum'de yaşar ama seçim motoru Catalog'da.
 * Catalog, Curriculum'ü tanımadığı için araya bu DTO giriyor.
 */
final readonly class BlueprintSpec
{
    /** @param  list<BlueprintItemSpec>  $items */
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public int $durationMin,
        public float $penaltyRatio,
        public int $baseScore,
        public array $items,
    ) {}

    public function totalQuestions(): int
    {
        return array_sum(array_map(
            static fn (BlueprintItemSpec $i): int => $i->questionCount,
            $this->items,
        ));
    }
}
