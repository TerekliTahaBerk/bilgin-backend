<?php

declare(strict_types=1);

namespace App\Modules\League\Application\Listener;

use App\Modules\League\Application\UseCase\RecordLeagueXp;
use App\Shared\Domain\Event\XpAwarded;

/**
 * Gamification → League bağlantısının TEK noktası.
 *
 * Gamification lig diye bir şey olduğunu bilmiyor; yalnızca olay yayınlıyor.
 * Lig kaldırılsa XP hesabında tek satır değişmez.
 */
final readonly class RecordLeagueXpListener
{
    public function __construct(private RecordLeagueXp $record) {}

    public function handle(XpAwarded $event): void
    {
        ($this->record)($event);
    }
}
