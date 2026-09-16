<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Domain\Streak;

use DateTimeImmutable;

/**
 * Seri (streak) hesabı — saf, tarih aritmetiği dışında hiçbir bağımlılığı yok.
 *
 * Gün sınırı KULLANICININ YEREL gününe göredir. Sunucu UTC saklar; İstanbul'da
 * gece 02:00'de çalışan öğrenci için o gün hâlâ dünse serisi kırılmamalıdır.
 * Bu yüzden karşılaştırma yerel tarih STRING'i üzerinden yapılır.
 *
 * Bir gün "sayılır" = en az 1 çalışma tamamlandı. Günlük hedef değil, 1 tur
 * yeter — tasarımdaki "Seriyi korumak için bir tur yeter" sözü.
 */
final readonly class StreakTracker
{
    /** @var list<int> */
    private const MILESTONES = [7, 12, 30, 100, 365];

    public function extend(StreakState $state, DateTimeImmutable $localNow): StreakState
    {
        $today = $localNow->format('Y-m-d');

        if ($state->lastStudyDate === $today) {
            // Bugün zaten sayıldı: aynı gün ikinci tur seriyi ilerletmez.
            return new StreakState($state->current, $state->longest, $today, false);
        }

        $yesterday = $localNow->modify('-1 day')->format('Y-m-d');

        $current = $state->lastStudyDate === $yesterday
            ? $state->current + 1
            : 1;   // ilk gün ya da seri kırıldı

        return new StreakState(
            current: $current,
            longest: max($state->longest, $current),
            lastStudyDate: $today,
            extendedToday: true,
            milestone: in_array($current, self::MILESTONES, true) ? $current : null,
        );
    }

    /** Seri bugün itibarıyla hâlâ canlı mı? (bildirim kararı için) */
    public function isAlive(StreakState $state, DateTimeImmutable $localNow): bool
    {
        if ($state->lastStudyDate === null) {
            return false;
        }

        return in_array($state->lastStudyDate, [
            $localNow->format('Y-m-d'),
            $localNow->modify('-1 day')->format('Y-m-d'),
        ], true);
    }
}
