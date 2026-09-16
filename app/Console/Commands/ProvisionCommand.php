<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Catalog\Database\Seeders\SubjectSeeder;
use App\Modules\Catalog\Database\Seeders\UnitTemplateSeeder;
use App\Modules\Catalog\Database\Seeders\YksCourseSeeder;
use App\Modules\Curriculum\Database\Seeders\YksBlueprintSeeder;
use App\Modules\Curriculum\Database\Seeders\YksCurriculumMapSeeder;
use App\Modules\Curriculum\Database\Seeders\YksExamSeeder;
use App\Modules\Gamification\Database\Seeders\BadgeSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Konteyner açılışında çalışan kurulum komutu.
 *
 * Amaç: dağıtımı "env'i gir ve deploy et"e indirmek. Elle çalıştırılması
 * gereken adım kalmasın; unutulan bir `migrate --force` uygulamayı her
 * istekte 500'e düşürür ve sebebi log'a bakmadan anlaşılmaz.
 *
 * İki kez çalıştırılması güvenlidir:
 *   - migration'lar zaten idempotent,
 *   - yapı seed'leri updateOrCreate kullanıyor,
 *   - seed yalnızca müfredat BOŞSA çalışır, yani var olan veriyi ezmez.
 */
final class ProvisionCommand extends Command
{
    protected $signature = 'app:provision {--skip-seed : Yapı verisini yükleme}';

    protected $description = 'Migration, yapı verisi ve önbellekleri hazırlar (konteyner açılışı)';

    /**
     * Müfredat iskeleti. PilotContentSeeder BİLEREK yok — o test içeriği
     * ve üretime gitmemeli.
     *
     * @var list<class-string<Seeder>>
     */
    private const STRUCTURE_SEEDERS = [
        YksExamSeeder::class,
        SubjectSeeder::class,
        YksCourseSeeder::class,
        YksCurriculumMapSeeder::class,
        YksBlueprintSeeder::class,
        UnitTemplateSeeder::class,
        BadgeSeeder::class,
    ];

    public function handle(): int
    {
        if (! $this->waitForDatabase()) {
            $this->error('Veritabanına ulaşılamadı.');

            return self::FAILURE;
        }

        $this->components->task('Migration', function (): bool {
            $this->callSilent('migrate', ['--force' => true]);

            return true;
        });

        if (! $this->option('skip-seed')) {
            $this->seedStructure();
        }

        // config:cache üretimde şart: her istekte onlarca config dosyası
        // okumak yerine tek dosya yüklenir.
        foreach (['config:cache', 'route:cache', 'event:cache'] as $command) {
            $this->components->task($command, function () use ($command): bool {
                $this->callSilent($command);

                return true;
            });
        }

        $this->newLine();
        $this->components->info('Hazır.');

        if ($this->noAdminExists()) {
            $this->components->warn(
                'Panelde hiç yönetici yok. Oluşturmak için: php artisan admin:create'
            );
        }

        return self::SUCCESS;
    }

    /**
     * Veritabanı hazır olana kadar bekler.
     *
     * Docker'da uygulama konteyneri veritabanından önce ayağa kalkabilir;
     * beklemeden başlamak açılışta kırılmak demek.
     */
    private function waitForDatabase(int $attempts = 30): bool
    {
        for ($i = 1; $i <= $attempts; $i++) {
            try {
                DB::connection()->getPdo();

                return true;
            } catch (Throwable $e) {
                $i === 1 && $this->components->info('Veritabanı bekleniyor…');
                sleep(2);
            }
        }

        return false;
    }

    /**
     * Müfredat iskeletini yükler — yalnızca boşsa.
     *
     * "Boşsa" kontrolü, her açılışta seeder koşturmanın maliyetini ve
     * beklenmedik veri değişikliği riskini ortadan kaldırır.
     */
    private function seedStructure(): void
    {
        if (DB::table('exams')->exists()) {
            $this->components->twoColumnDetail('Yapı verisi', '<fg=gray>zaten yüklü</>');

            return;
        }

        foreach (self::STRUCTURE_SEEDERS as $seeder) {
            $this->components->task(class_basename($seeder), function () use ($seeder): bool {
                $this->callSilent('db:seed', ['--class' => $seeder, '--force' => true]);

                return true;
            });
        }
    }

    private function noAdminExists(): bool
    {
        return Schema::hasTable('admin_users') && ! DB::table('admin_users')->exists();
    }
}
