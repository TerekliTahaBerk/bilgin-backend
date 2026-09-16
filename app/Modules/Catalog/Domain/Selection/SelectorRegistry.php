<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Selection;

use App\Modules\Catalog\Domain\Enum\SelectionMode;
use RuntimeException;

/**
 * Moda göre selector çözer. Kod tabanında SelectionMode üzerinde switch
 * yapılan tek yer burasıdır — ve burada da switch yoktur, dizi araması vardır.
 */
final class SelectorRegistry
{
    /** @var array<string, ExerciseSelector> */
    private array $selectors = [];

    /** @param iterable<ExerciseSelector> $selectors */
    public function __construct(iterable $selectors = [])
    {
        foreach ($selectors as $selector) {
            $this->register($selector);
        }
    }

    public function register(ExerciseSelector $selector): void
    {
        $this->selectors[$selector->mode()->value] = $selector;
    }

    public function for(SelectionMode $mode): ExerciseSelector
    {
        return $this->selectors[$mode->value]
            ?? throw new RuntimeException("Bu mod için selector kayıtlı değil: {$mode->value}");
    }

    public function supports(SelectionMode $mode): bool
    {
        return isset($this->selectors[$mode->value]);
    }
}
