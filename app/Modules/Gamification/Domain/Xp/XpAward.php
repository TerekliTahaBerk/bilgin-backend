<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Domain\Xp;

/** Hesaplanan XP ve nasıl oluştuğu — sonuç ekranı dökümü gösterir. */
final readonly class XpAward
{
    /** @param  array<string, int>  $breakdown */
    public function __construct(
        public int $total,
        public array $breakdown,
    ) {}

    public static function zero(): self
    {
        return new self(0, []);
    }

    public function plus(string $code, int $amount): self
    {
        if ($amount === 0) {
            return $this;
        }

        return new self($this->total + $amount, [...$this->breakdown, $code => $amount]);
    }

    public function scaled(string $code, float $factor): self
    {
        $scaled = (int) round($this->total * $factor);

        return new self($scaled, [...$this->breakdown, $code => $scaled - $this->total]);
    }
}
