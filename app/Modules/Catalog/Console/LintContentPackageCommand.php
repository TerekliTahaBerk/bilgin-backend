<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ValidatorRegistry;
use Illuminate\Console\Command;
use JsonException;

/**
 * İçerik paketini veritabanına dokunmadan denetler.
 *
 * ## Neden ayrı bir komut
 *
 * `ContentPackageSeeder` paketi içe aktarırken zaten doğruluyor ama
 * oraya gelene kadar bir veritabanı, çalışan göçler ve seeder sırası
 * gerekiyor. İçerik yazan kişi (ya da bu paketleri dolduran bir ajan)
 * yazdığı dosyanın geçerli olup olmadığını saniyeler içinde görmeli.
 *
 * Komut iki soruya cevap veriyor:
 *
 *   1. **Geçerli mi** — tipine göre şema doğrulaması, tanımsız konu
 *      kodu, dosya içinde tekrar eden soru.
 *   2. **Yeterli mi** — ünite şablonundaki node'lar belli tipleri belli
 *      ZORLUK ARALIKLARINDA istiyor. 40 eşleştirme sorusu yazıp hepsini
 *      zorluk 5'e koymak, zorluk 2-3 isteyen node'u boş bırakıyor; bu
 *      hata şema doğrulamasından geçiyor ve ancak öğrenci node'u
 *      açtığında ortaya çıkıyor.
 *
 * Hedefler `standart_unite` şablonundan geliyor ve node'un istediği
 * soru sayısının üç katı olarak alınıyor: tam sayıda soru varsa öğrenci
 * her turda aynı soruları görüyor, tekrar aralığı çalışmıyor.
 */
final class LintContentPackageCommand extends Command
{
    protected $signature = 'content:paket-dogrula
        {path?* : Denetlenecek paket dosyaları (boşsa database/content/*.json)}';

    protected $description = 'İçerik paketini veritabanına dokunmadan denetler';

    /**
     * Node'ların istediği havuzlar: [tip, asgari soru, zorluk alt, zorluk üst].
     *
     * `standart_unite` şablonundaki `selection_rule.filters` değerlerinin
     * birebir yansıması. Şablon değişirse burası da değişmeli — iki yerde
     * durması hoş değil ama şablon veritabanında, bu komut ise
     * veritabanına bakmadan çalışabilmeli.
     *
     * ## Neden aynı tip iki kez geçiyor
     *
     * Liste başta `tip => hedef` sözlüğüydü ve bir tipe TEK bant
     * tanımlayabiliyordu. Bu, görülmesi zor bir kusuru gizliyordu:
     *
     * "Ünite Challenge" node'u tip kısıtı olmadan zorluk 4-5'ten 10 soru
     * çekiyor. Çalışma node'ları için yazılan sorular ise 1-3 bandında.
     * Sonuç: challenge havuzunun %94'ü çoktan seçmeli ve sayısal giriş
     * oluyor, öğrencinin ünite sonu sınavı tek tipe dönüyor. Sözlük
     * yapısında bu hiç raporlanamıyordu çünkü `true_false` satırı
     * "1-2 bandında 18 tane var, tamam" diyordu.
     *
     * Bu yüzden hedefler artık (tip + bant) çifti. `word_order`,
     * `image_hotspot` ve `diagram_label` için YALNIZCA 4-5 bandı var:
     * bu üç tipi isteyen başka hiçbir node yok, dolayısıyla 1-3 bandına
     * yazılan böyle bir soru hiçbir zaman öğrenciye gelmiyor.
     *
     * @var list<array{0: string, 1: int, 2: int, 3: int}>
     */
    private const TARGETS = [
        // Çalışma node'ları — kolaydan zora ilerleme.
        ['true_false', 18, 1, 2],
        ['fill_blank', 21, 1, 3],
        ['matching', 12, 2, 3],
        ['ordering', 21, 3, 4],

        // Ünite Challenge — tip kısıtı yok, zorluk 4-5. Çeşitlilik için.
        ['true_false', 5, 4, 5],
        ['fill_blank', 5, 4, 5],
        ['matching', 5, 4, 5],
        ['word_order', 6, 4, 5],
        ['image_hotspot', 4, 4, 5],
        ['diagram_label', 4, 4, 5],
    ];

    public function handle(ValidatorRegistry $validators): int
    {
        /** @var list<string> $paths */
        $paths = $this->argument('path');

        if ($paths === []) {
            $paths = array_values(array_filter(
                glob(database_path('content/*.json')) ?: [],
                static fn (string $p): bool => ! str_starts_with(basename($p), '_'),
            ));
            sort($paths);
        }

        if ($paths === []) {
            $this->error('Denetlenecek paket yok.');

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($paths as $path) {
            if (! $this->lint($path, $validators)) {
                $failed++;
            }
        }

        $this->newLine();

        if ($failed > 0) {
            $this->error("{$failed} paket denetimden geçemedi.");

            return self::FAILURE;
        }

        $this->info(count($paths).' paket denetimden geçti.');

        return self::SUCCESS;
    }

    private function lint(string $path, ValidatorRegistry $validators): bool
    {
        $name = basename($path);

        try {
            $package = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->line("<fg=red>✗</> {$name} — JSON bozuk: {$e->getMessage()}");

            return false;
        }

        if (! is_array($package)) {
            $this->line("<fg=red>✗</> {$name} — kök bir nesne olmalı");

            return false;
        }

        $problems = [];

        foreach (['course', 'subject', 'unit', 'topics', 'exercises'] as $key) {
            if (! isset($package[$key])) {
                $problems[] = "'{$key}' alanı yok";
            }
        }

        if ($problems !== []) {
            $this->line("<fg=red>✗</> {$name}");

            foreach ($problems as $p) {
                $this->line("    · {$p}");
            }

            return false;
        }

        $topicCodes = [];

        foreach ((array) $package['topics'] as $topic) {
            if (is_array($topic) && is_string($topic['code'] ?? null)) {
                $topicCodes[$topic['code']] = true;
            }
        }

        /*
         | Burada `@var list<array<string,mixed>>` yazmak YANLIŞ olurdu:
         | denetlenen dosya elle yazılıyor ve `"exercises": [1, 2, 3]`
         | içerebilir. Tip bildirimi aşağıdaki `is_array` kontrolünü
         | gereksiz gösterir, kontrol kalkarsa da komut bozuk dosyada
         | kendisi patlar — oysa işi bozuk dosyayı RAPORLAMAK.
        */
        $exercises = (array) $package['exercises'];

        $counts = [];
        $banded = [];
        $seen = [];

        foreach ($exercises as $i => $exercise) {
            if (! is_array($exercise)) {
                $problems[] = "#{$i}: bir nesne olmalı";

                continue;
            }

            $topic = $exercise['topic'] ?? null;

            // Pakette tanımsız konu kodu, içe aktarmayı TÜM paket için
            // patlatıyor; burada yakalamak 40 sorunun tamamının
            // kaybedilmesini önlüyor.
            if (! is_string($topic) || ! isset($topicCodes[$topic])) {
                $problems[] = sprintf('#%d: pakette tanımsız konu "%s"', $i, is_string($topic) ? $topic : '(yok)');
            }

            $type = ExerciseType::tryFrom((string) ($exercise['type'] ?? ''));

            if ($type === null) {
                $problems[] = sprintf('#%d: bilinmeyen tip "%s"', $i, $exercise['type'] ?? '');

                continue;
            }

            $content = (array) ($exercise['content'] ?? []);
            $answerKey = (array) ($exercise['answer_key'] ?? []);

            foreach ($validators->for($type)->validate($content, $answerKey)->errors as $error) {
                $problems[] = sprintf('#%d (%s): %s', $i, $type->value, $error);
            }

            $difficulty = $exercise['difficulty'] ?? null;

            if (! is_int($difficulty) || $difficulty < 1 || $difficulty > 5) {
                $problems[] = sprintf('#%d (%s): zorluk 1-5 arası bir tam sayı olmalı', $i, $type->value);
            }

            $scopes = $exercise['scopes'] ?? null;

            if (! is_array($scopes) || $scopes === []) {
                $problems[] = sprintf('#%d (%s): kapsam (scopes) boş olamaz', $i, $type->value);
            }

            /*
             | Aynı soru iki kez yazılmışsa içe aktarma onu SESSİZCE
             | atlıyor (parmak izi aynı) ve hedef tutmuş görünürken
             | havuz eksik kalıyor. Bu yüzden burada hata sayılıyor.
            */
            $fingerprint = hash('sha256', (string) $topic.$type->value.json_encode($content));

            if (isset($seen[$fingerprint])) {
                $problems[] = sprintf('#%d (%s): #%d ile birebir aynı', $i, $type->value, $seen[$fingerprint]);
            } else {
                $seen[$fingerprint] = $i;
            }

            $counts[$type->value] = ($counts[$type->value] ?? 0) + 1;

            if (! is_int($difficulty)) {
                continue;
            }

            // Bir soru birden çok hedefe sayılabilir (bantlar çakışabilir);
            // her hedef kendi aralığını ayrı sayıyor.
            foreach (self::TARGETS as $i => [$targetType, , $min, $max]) {
                if ($type->value === $targetType && $difficulty >= $min && $difficulty <= $max) {
                    $banded[$i] = ($banded[$i] ?? 0) + 1;
                }
            }
        }

        $short = [];

        foreach (self::TARGETS as $i => [$type, $target, $min, $max]) {
            $got = $banded[$i] ?? 0;

            if ($got < $target) {
                $short[] = sprintf('%s %d/%d (zorluk %d-%d)', $type, $got, $target, $min, $max);
            }
        }

        if ($problems !== []) {
            $this->line("<fg=red>✗</> {$name} — ".count($exercises).' soru');

            foreach (array_slice($problems, 0, 12) as $p) {
                $this->line("    <fg=red>·</> {$p}");
            }

            if (count($problems) > 12) {
                $this->line('    <fg=gray>… ve '.(count($problems) - 12).' sorun daha</>');
            }

            return false;
        }

        if ($short !== []) {
            // Geçersiz DEĞİL, yalnızca eksik: paket içe aktarılabilir ama
            // o node'lar zayıf havuzla açılır.
            $this->line("<fg=yellow>!</> {$name} — ".count($exercises).' soru, hedefin altında');

            foreach ($short as $s) {
                $this->line("    <fg=yellow>·</> {$s}");
            }

            return true;
        }

        $this->line("<fg=green>✓</> {$name} — ".count($exercises).' soru');

        return true;
    }
}
