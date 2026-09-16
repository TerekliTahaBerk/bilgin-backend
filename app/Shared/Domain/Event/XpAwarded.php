<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

use DateTimeImmutable;

/**
 * Modüller arası olay: bir kullanıcı XP kazandı.
 *
 * Shared'da duruyor çünkü yayıncı (Gamification) ile dinleyici (League)
 * birbirini TANIMAMALI. Gamification lig diye bir şey olduğunu bilmez;
 * League de XP'nin nasıl hesaplandığını.
 */
final readonly class XpAwarded
{
    public function __construct(
        public int $userId,
        public int $amount,
        public string $sourceType,
        public int $sourceId,
        /**
         * Lig XP'si yalnızca oturumdan gelir; rozet ve görev XP'si sayılmaz.
         * Manipülasyon yüzeyini daraltır.
         */
        public bool $countsForLeague,
        public string $timezone,
        public DateTimeImmutable $awardedAt,
    ) {}
}
