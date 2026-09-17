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
     *
     * Başarısız olursa hedefi ve GERÇEK sebebi basar. "Veritabanına
     * ulaşılamadı" tek başına hiçbir şey anlatmıyor: ağ mı, şifre mi,
     * isim çözümlemesi mi — ayırt edilemiyordu.
     *
     * 10 deneme × (2sn yoklama + 2sn bekleme) ≈ en fazla 40sn. Üst sınırın
     * olması şart: dağıtım aracı konteyneri sağlıksız sayıp öldürürse hatayı
     * yazan satıra hiç sıra gelmez ve sorun görünmez kalır.
     */
    private function waitForDatabase(int $attempts = 10): bool
    {
        $connection = (string) config('database.default');
        $config = (array) config("database.connections.{$connection}");

        $host = (string) ($config['host'] ?? '');
        $port = (int) ($config['port'] ?? 5432);

        $target = sprintf(
            '%s://%s@%s:%s/%s',
            $connection,
            $config['username'] ?? '?',
            $host !== '' ? $host : '?',
            $port,
            $config['database'] ?? '?',
        );

        $reason = null;

        for ($i = 1; $i <= $attempts; $i++) {
            $reason = $this->probeDatabase($host, $port);

            if ($reason === null) {
                return true;
            }

            if ($i === 1) {
                $this->components->info("Veritabanı bekleniyor: {$target}");
            }

            sleep(2);
        }

        $this->newLine();
        $this->components->error('Veritabanına ulaşılamadı.');
        $this->components->twoColumnDetail('Hedef', $target);
        $this->components->twoColumnDetail('Sebep', $reason ?? 'bilinmiyor');

        return false;
    }

    /**
     * Tek bağlantı denemesi. Başarılıysa null, değilse okunur sebep döner.
     *
     * Önce ham TCP yoklaması yapılıyor çünkü PDO'ya zaman aşımı veremiyoruz:
     * Laravel'in pgsql DSN üreticisi `connect_timeout` yazmıyor, libpq da
     * varsayılanda süresiz bekliyor. Ulaşılamayan bir adreste `getPdo()`
     * dakikalarca asılır — ve o sırada hata satırı hiç basılamaz.
     *
     * fsockopen'ın ayrıca teşhis değeri var: ad çözülemedi / bağlantı
     * reddedildi / zaman aşımı birbirinden ayrılıyor, üçü de farklı sorun.
     */
    private function probeDatabase(string $host, int $port): ?string
    {
        $errno = 0;
        $errstr = '';

        $socket = @fsockopen($host, $port, $errno, $errstr, 2.0);

        if ($socket === false) {
            return $this->explainUnreachable($errstr, $host);
        }

        fclose($socket);

        // Port açık; buradan sonrası hızlı başarısız olur — kimlik doğrulama
        // ve "veritabanı yok" hataları bağlantı kurulduktan sonra anında döner.
        try {
            DB::connection()->getPdo();

            return null;
        } catch (Throwable $e) {
            return $this->explainPdoError($e->getMessage());
        }
    }

    /** TCP hiç kurulamadı: ağ katmanında ne olduğunu söyler. */
    private function explainUnreachable(string $errstr, string $host): string
    {
        $raw = trim($errstr) !== '' ? trim($errstr) : 'yanıt yok';

        if (str_contains($errstr, 'getaddrinfo') || str_contains($errstr, 'Name or service not known')) {
            return "'{$host}' adı çözülemedi — uygulama ve veritabanı aynı Docker ağında değil. "
                .'Coolify → uygulama → Advanced → "Connect To Predefined Network" aç, yeniden deploy et.';
        }

        if (str_contains($errstr, 'refused')) {
            return "{$host} çözüldü ama port kapalı ({$raw}) — veritabanı konteyneri çalışmıyor "
                .'ya da DB_PORT yanlış.';
        }

        return "{$host} adresine ulaşılamadı ({$raw}) — ad çözülüyor ama paketler dönmüyor. "
            .'Genellikle iki kaynağın farklı ağlarda olmasından kaynaklanır.';
    }

    /** TCP kuruldu ama oturum açılamadı: kimlik ya da veritabanı adı sorunu. */
    private function explainPdoError(string $message): string
    {
        if (str_contains($message, 'password authentication failed')) {
            return 'Şifre reddedildi — DB_PASSWORD yanlış, ya da Coolify\'de '
                .'"Build Variable" işaretli olduğu için çalışma anında görünmüyor.';
        }

        if (str_contains($message, 'does not exist')) {
            return "DB_DATABASE yanlış: {$message}";
        }

        return $message;
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
