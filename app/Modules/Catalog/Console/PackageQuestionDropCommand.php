<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\Application\QuestionDrop\DropReader;
use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ValidatorRegistry;
use Illuminate\Console\Command;
use JsonException;

/**
 * Soru paketini sunucuya taşınabilir tek bir dosyaya indirger.
 *
 * ## Neden gerekli
 *
 * İçerik ekibinin gönderdiği klasör 308 MB ve 1652 dosya; büyük kısmı
 * ilerleme raporu, doğrulama çıktısı ve eski sürüm. Sunucuda o klasör
 * YOK ve olmamalı da — depoya koymak 308 MB'lık bir geçmiş yükü demek.
 *
 * Bu komut klasörü bir kez okuyup yalnızca ONAYLI EŞLEMESİ OLAN soruları
 * normalize edilmiş NDJSON olarak yazıyor. Ölçüldü: 16.372 soru ham
 * 15,9 MB, gzip'lenmiş **3,6 MB**. Depoya girebilir bir boyut.
 *
 * Arşivdeki her satır ARTIK BİZİM ŞEMAMIZDA: konu kodu çözülmüş,
 * alan adları çevrilmiş, doğrulamadan geçmiş. Sunucudaki içe aktarma
 * ne BOM'la ne karışık dosya yapısıyla ne de eşleme dosyasıyla
 * uğraşıyor — o işlerin hepsi burada, bir kez yapılıyor.
 *
 * ## Akış
 *
 *   yerelde:  content:paket-tara → (eşleme) → content:paket-paketle
 *             git add database/question-drop/sorular.ndjson.gz && push
 *   sunucuda: content:paket-aktar database/question-drop/sorular.ndjson.gz
 *             content:tip-turet
 */
final class PackageQuestionDropCommand extends Command
{
    protected $signature = 'content:paket-paketle
        {path : Soru paketinin kök klasörü}
        {--map=database/question-drop/topic-map.json : Onaylanmış eşleme}
        {--out=database/question-drop/sorular.ndjson.gz : Üretilecek arşiv}';

    protected $description = 'Soru paketini sunucuya taşınabilir tek dosyaya indirger';

    public function handle(DropReader $reader, ValidatorRegistry $validators): int
    {
        $path = rtrim($this->argument('path'), '/');

        if (! is_dir($path)) {
            $this->error("Klasör bulunamadı: {$path}");

            return self::FAILURE;
        }

        $map = $this->loadMap();

        if ($map === null) {
            return self::FAILURE;
        }

        $out = (string) $this->option('out');
        @mkdir(dirname($out), 0755, true);

        $handle = gzopen($out, 'wb9');

        if ($handle === false) {
            $this->error("Arşiv açılamadı: {$out}");

            return self::FAILURE;
        }

        $this->info('Paketleniyor…');

        $stats = ['okunan' => 0, 'yazılan' => 0, 'eşleme_yok' => 0, 'geçersiz' => 0, 'tekrar' => 0];
        $seen = [];
        $byType = [];

        foreach ($reader->read($path) as ['question' => $q]) {
            $stats['okunan']++;

            $teamCode = is_string($q['topic_code'] ?? null) ? trim($q['topic_code']) : '';
            $name = is_string($q['topic'] ?? null) ? trim($q['topic']) : '';
            $key = $teamCode !== '' ? $teamCode : 'ad:'.$name;

            $topicCode = $map[$key] ?? null;

            if ($topicCode === null) {
                $stats['eşleme_yok']++;

                continue;
            }

            $exercise = is_array($q['exercise'] ?? null) ? $q['exercise'] : [];
            $type = ExerciseType::tryFrom((string) ($exercise['type'] ?? ''));

            if ($type === null) {
                $stats['geçersiz']++;

                continue;
            }

            $content = (array) ($exercise['content'] ?? []);
            $answerKey = (array) ($exercise['answer_key'] ?? []);

            /*
             | Doğrulama burada BİR KEZ yapılıyor.
             |
             | Bozuk soru arşive hiç girmiyor; böylece sunucudaki
             | aktarma bir doğrulama raporuyla değil temiz bir sayıyla
             | dönüyor. Aktarma yine de kendi doğrulamasını çalıştırıyor:
             | arşiv depoda duruyor ve kurallar sonradan değişebilir.
            */
            if (! $validators->for($type)->validate($content, $answerKey)->passes()) {
                $stats['geçersiz']++;

                continue;
            }

            // Tekrarlar da burada ayıklanıyor: aynı soru paketin birden
            // çok dosyasında geçiyor ve arşivi gereksiz büyütürdü.
            $fingerprint = hash('sha256', $topicCode.$type->value.json_encode($content));

            if (isset($seen[$fingerprint])) {
                $stats['tekrar']++;

                continue;
            }

            $seen[$fingerprint] = true;

            $row = [
                't' => $topicCode,
                'y' => $type->value,
                'd' => (int) ($exercise['difficulty'] ?? 3),
                's' => (array) ($exercise['applicable_scopes'] ?? ['tyt']),
                'c' => $content,
                'a' => $answerKey,
            ];

            if (is_string($exercise['explanation'] ?? null) && trim($exercise['explanation']) !== '') {
                $row['e'] = trim($exercise['explanation']);
            }

            gzwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE).PHP_EOL);

            $stats['yazılan']++;
            $byType[$type->value] = ($byType[$type->value] ?? 0) + 1;
        }

        gzclose($handle);

        $size = filesize($out);

        file_put_contents(
            preg_replace('/\.ndjson\.gz$/', '.meta.json', $out) ?? $out.'.meta.json',
            json_encode([
                '_note' => 'Arsiv ozeti. Sunucuda: php artisan content:paket-aktar <arsiv>',
                'packaged_at' => now()->toIso8601String(),
                'questions' => $stats['yazılan'],
                'by_type' => $byType,
                'source_files' => $reader->fileCount(),
                'unreadable_files' => count($reader->skipped()),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '',
        );

        $this->newLine();

        foreach ($stats as $label => $value) {
            $this->components->twoColumnDetail(
                '<fg=gray>'.str_replace('_', ' ', $label).'</>',
                (string) $value,
            );
        }

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=green>arşiv</>',
            $out.'  ('.($size === false ? '?' : round($size / 1024 / 1024, 1)).' MB)',
        );

        $this->newLine();
        $this->line('Şimdi: <fg=cyan>git add '.dirname($out).' && git commit && git push</>');
        $this->line('Sunucuda: <fg=cyan>php artisan content:paket-aktar '.$out.'</>');

        return self::SUCCESS;
    }

    /** @return array<string, string>|null ekip kodu => bizim konu kodumuz */
    private function loadMap(): ?array
    {
        $file = (string) $this->option('map');

        if (! is_file($file)) {
            $this->error("Eşleme dosyası yok: {$file}");

            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error("Eşleme okunamadı: {$e->getMessage()}");

            return null;
        }

        $map = [];

        foreach ((array) ($decoded['topics'] ?? []) as $row) {
            $code = $row['our_code'] ?? null;

            if (is_string($code) && $code !== '') {
                $map[(string) $row['team_code']] = $code;
            }
        }

        if ($map === []) {
            $this->error('Eşleme dosyasında doldurulmuş satır yok.');

            return null;
        }

        return $map;
    }
}
