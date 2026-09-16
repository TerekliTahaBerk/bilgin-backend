<?php

declare(strict_types=1);

namespace App\Modules\League\Application\UseCase;

final readonly class CloseReport
{
    public function __construct(
        public string $weekStart,
        public int $leaguesClosed,
        public int $promoted,
        public int $demoted,
    ) {}
}
