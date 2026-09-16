<?php

declare(strict_types=1);

namespace App\Modules\Hearts\Domain;

use DateInterval;
use DateTimeImmutable;

/**
 * Tembel rejenerasyon — saf hesap, veritabanı ve cron yok.
 *
 * Bakiye okunurken hesaplanır:
 *   kazanılan = floor((şimdi − son_yenileme) / aralık)
 *
 * Kalan süre KORUNUR: son_yenileme, kazanılan can kadar ileri alınır,
 * "şimdi"ye eşitlenmez. Aksi hâlde uygulamayı sık açan kullanıcı her
 * seferinde sayacı sıfırlar ve sebepsiz yere cezalandırılmış olur.
 */
final readonly class HeartRegeneration
{
    public function __construct(
        private int $max,
        private int $intervalMinutes,
    ) {}

    public function apply(int $hearts, DateTimeImmutable $lastRegenAt, DateTimeImmutable $now): RegenerationResult
    {
        if ($hearts >= $this->max) {
            // Dolu bakiyede sayaç şimdiden başlar; aksi hâlde biriken süre
            // ilk yanlıştan sonra anında can olarak geri gelirdi.
            return new RegenerationResult($this->max, $now, 0);
        }

        $elapsed = $now->getTimestamp() - $lastRegenAt->getTimestamp();

        if ($elapsed < 0) {
            return new RegenerationResult($hearts, $lastRegenAt, 0);
        }

        $intervalSeconds = $this->intervalMinutes * 60;
        $earned = intdiv($elapsed, $intervalSeconds);

        if ($earned === 0) {
            return new RegenerationResult($hearts, $lastRegenAt, 0);
        }

        $newHearts = min($this->max, $hearts + $earned);
        $actuallyEarned = $newHearts - $hearts;

        $newLastRegen = $newHearts >= $this->max
            ? $now
            : $lastRegenAt->add(new DateInterval('PT'.($actuallyEarned * $intervalSeconds).'S'));

        return new RegenerationResult($newHearts, $newLastRegen, $actuallyEarned);
    }

    public function nextHeartAt(int $hearts, DateTimeImmutable $lastRegenAt): ?DateTimeImmutable
    {
        if ($hearts >= $this->max) {
            return null;
        }

        return $lastRegenAt->add(new DateInterval('PT'.($this->intervalMinutes * 60).'S'));
    }

    public function fullAt(int $hearts, DateTimeImmutable $lastRegenAt): ?DateTimeImmutable
    {
        if ($hearts >= $this->max) {
            return null;
        }

        $missing = $this->max - $hearts;

        return $lastRegenAt->add(new DateInterval('PT'.($missing * $this->intervalMinutes * 60).'S'));
    }

    public function max(): int
    {
        return $this->max;
    }
}
