<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Domain\Badge;

/**
 * Bir rozetin durumu.
 *
 * Kazanılmamış rozetlerde de İLERLEME döner: tasarımda kilitli rozetler
 * görünüyor ve "100 doğru" rozetinin 73'te olduğunu görmek, hiç bilgi
 * vermeyen gri bir ikondan çok daha güçlü bir motivasyon.
 */
final readonly class BadgeEvaluation
{
    public function __construct(
        public bool $earned,
        public int $current,
        public int $target,
    ) {}

    public function progress(): float
    {
        return $this->target === 0 ? 1.0 : min(1.0, $this->current / $this->target);
    }

    public function percent(): int
    {
        return (int) round($this->progress() * 100);
    }
}
