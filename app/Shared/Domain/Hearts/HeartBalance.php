<?php

declare(strict_types=1);

namespace App\Shared\Domain\Hearts;

use DateTimeImmutable;

/**
 * Can bakiyesinin salt-okunur görüntüsü.
 *
 * Shared'da duruyor çünkü Learning oturum yanıtında, Identity profil
 * ekranında kullanıyor; hiçbiri Hearts modülünün iç yapısını tanımadan.
 */
final readonly class HeartBalance
{
    public function __construct(
        public int $hearts,
        public int $max,
        public bool $unlimited = false,
        public ?DateTimeImmutable $nextHeartAt = null,
        public ?DateTimeImmutable $fullAt = null,
    ) {}

    public static function unlimited(int $max): self
    {
        return new self($max, $max, true);
    }

    public function isDepleted(): bool
    {
        return ! $this->unlimited && $this->hearts <= 0;
    }

    public function isFull(): bool
    {
        return $this->unlimited || $this->hearts >= $this->max;
    }
}
