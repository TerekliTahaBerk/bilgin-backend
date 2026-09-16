<?php

declare(strict_types=1);

namespace App\Modules\League\Domain;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Lig haftası — Pazartesi 00:00 Europe/Istanbul'da başlar ve biter.
 *
 * Hafta sınırı UTC'ye göre hesaplansaydı, Türkiye'de Pazar gecesi 23:00'te
 * kazanılan XP bir sonraki haftaya yazılırdı; öğrenci son anda sıralamayı
 * değiştirmek isterken puanını başka haftaya vermiş olurdu.
 */
final readonly class LeagueWeek
{
    private const TIMEZONE = 'Europe/Istanbul';

    private function __construct(public string $startsOn) {}

    public static function containing(DateTimeImmutable $moment): self
    {
        $local = $moment->setTimezone(new DateTimeZone(self::TIMEZONE));

        // 'monday this week' ISO-8601'e göre çalışır: Pazar günü de
        // içinde bulunduğu haftanın Pazartesi'sini verir.
        return new self($local->modify('monday this week')->format('Y-m-d'));
    }

    public function previous(): self
    {
        return new self((new DateTimeImmutable($this->startsOn))->modify('-7 days')->format('Y-m-d'));
    }

    /** Haftanın bitiş anı (UTC) — geri sayım için. */
    public function endsAt(): DateTimeImmutable
    {
        return (new DateTimeImmutable($this->startsOn.' 00:00:00', new DateTimeZone(self::TIMEZONE)))
            ->modify('+7 days')
            ->setTimezone(new DateTimeZone('UTC'));
    }

    public function equals(self $other): bool
    {
        return $this->startsOn === $other->startsOn;
    }
}
