<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Application\UseCase\ImportContentPackage;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * `database/content` altındaki bütün soru paketlerini yükler.
 *
 * Dosya listesi ELLE TUTULMUYOR, klasör taranıyor: yeni bir paket eklemek
 * yalnızca JSON'u koymak olsun. Sabit liste, eklenen paketin unutulmasına
 * ve "neden üretimde yok?" sorusuna yol açıyordu.
 *
 * Paketlerde TYT ve AYT birlikte: alan filtresinin, sekme gruplamasının ve
 * blueprint'in AYT tarafında da çalıştığını erken görmek, 11 AYT dersini
 * yazdıktan sonra keşfetmekten çok ucuzdur.
 *
 * Paketler ContentPackageTest tarafından sınanıyor — konu kodları kanonik mi,
 * ünite yayın kapısından geçiyor mu, her sorunun açıklaması var mı.
 */
final class ContentPackageSeeder extends Seeder
{
    public function run(ImportContentPackage $import): void
    {
        $paths = glob(database_path('content/*.json')) ?: [];

        if ($paths === []) {
            throw new RuntimeException('database/content altında paket yok.');
        }

        sort($paths);

        foreach ($paths as $path) {
            $package = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $report = $import($package);

            $this->command->info(sprintf(
                '  %s → %d konu, %d node, %d soru',
                $report->unitTitle,
                $report->topicCount,
                $report->nodeCount,
                $report->exerciseCount,
            ));
        }
    }
}
