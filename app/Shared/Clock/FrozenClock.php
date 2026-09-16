<?php

declare(strict_types=1);

namespace App\Shared\Clock;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/** Testlerde kullanılır: zamanı dondurur ve elle ilerletir. */
final class FrozenClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(DateTimeImmutable|string $now = 'now')
    {
        $this->now = $now instanceof DateTimeImmutable
            ? $now
            : new DateTimeImmutable($now, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $interval): self
    {
        $this->now = $this->now->add(new DateInterval($interval));

        return $this;
    }

    public function set(DateTimeImmutable $now): self
    {
        $this->now = $now;

        return $this;
    }
}
