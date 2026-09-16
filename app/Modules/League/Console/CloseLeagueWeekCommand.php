<?php

declare(strict_types=1);

namespace App\Modules\League\Console;

use App\Modules\League\Application\UseCase\CloseLeagueWeek;
use Illuminate\Console\Command;

final class CloseLeagueWeekCommand extends Command
{
    protected $signature = 'league:close {--week= : Kapatılacak hafta (Y-m-d, Pazartesi)}';

    protected $description = 'Biten lig haftasını kapatır; terfi ve düşmeleri yazar';

    public function handle(CloseLeagueWeek $close): int
    {
        $report = $close($this->option('week'));

        $this->info(sprintf(
            '%s haftası: %d lig kapandı · %d terfi · %d düşme',
            $report->weekStart,
            $report->leaguesClosed,
            $report->promoted,
            $report->demoted,
        ));

        return self::SUCCESS;
    }
}
