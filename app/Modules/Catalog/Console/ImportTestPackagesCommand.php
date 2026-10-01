<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\Application\UseCase\ImportContentPackage;
use Illuminate\Console\Command;

/**
 * Geliştirme paketlerini yükler (`database/content/_*.json`).
 *
 * Normal seeder bu dosyaları ATLIYOR. Ayrı bir komut olmasının sebebi:
 * test ünitesi müfredatın parçası değil; üretimde bir öğrencinin
 * karşısına "Tüm Soru Tipleri (test)" diye bir ünite çıkmamalı.
 *
 * Üretimde `--force` isteniyor; yanlışlıkla çalıştırılması zor olmalı.
 */
final class ImportTestPackagesCommand extends Command
{
    protected $signature = 'content:test-paketi {--force : üretimde de çalıştır}';

    protected $description = 'Her soru tipini içeren test ünitesini yükler';

    public function handle(ImportContentPackage $import): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Üretimde test paketi yüklenmez. Gerekiyorsa --force.');

            return self::FAILURE;
        }

        $paths = array_values(array_filter(
            glob(database_path('content/*.json')) ?: [],
            static fn (string $path): bool => str_starts_with(basename($path), '_'),
        ));

        if ($paths === []) {
            $this->warn('Test paketi bulunamadı.');

            return self::SUCCESS;
        }

        foreach ($paths as $path) {
            $package = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $report = $import($package);

            $this->info(sprintf(
                '%s → %d konu, %d node, %d soru',
                $report->unitTitle,
                $report->topicCount,
                $report->nodeCount,
                $report->exerciseCount,
            ));
        }

        return self::SUCCESS;
    }
}
