<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCase;

use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;

final readonly class SelectionRuleReport
{
    public function __construct(
        public int $nodeId,
        public string $nodeTitle,
        public string $unitTitle,
        public string $courseName,
        public int $required,
        public int $available,
        public bool $relaxed = false,
        public ?string $error = null,
    ) {}

    public static function invalid(UnitNode $node, string $error): self
    {
        return new self($node->id, $node->title, '—', '—', $node->exercise_count, 0, false, $error);
    }

    public function passes(): bool
    {
        return $this->error === null && $this->available >= $this->required;
    }

    public function message(): string
    {
        if ($this->error !== null) {
            return $this->error;
        }

        if ($this->available < $this->required) {
            return "Kural {$this->available} soru getiriyor, {$this->required} gerekiyor.";
        }

        return $this->relaxed
            ? "Yeterli ({$this->available}/{$this->required}) — ancak zorluk filtresi gevşetilerek."
            : "Yeterli ({$this->available}/{$this->required}).";
    }
}
