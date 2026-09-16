<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Application\UseCase\ImportContentPackage;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Lansman pilot içeriği: TYT Tarih'in imza ünitesi + AYT Matematik'ten bir ünite.
 *
 * AYT ünitesi bilinçli olarak buradadır: alan filtresi, sekme gruplaması ve
 * blueprint'in AYT tarafında da çalıştığını lansmanda görmek, 11 AYT dersini
 * yazdıktan sonra keşfetmekten çok ucuzdur.
 */
final class PilotContentSeeder extends Seeder
{
    private const PACKAGES = [
        'tyt_tarih_turk_dunyasi.json',
        'ayt_matematik_turev.json',
    ];

    public function run(ImportContentPackage $import): void
    {
        foreach (self::PACKAGES as $file) {
            $path = database_path("content/{$file}");

            if (! is_file($path)) {
                throw new RuntimeException("İçerik paketi bulunamadı: {$path}");
            }

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
