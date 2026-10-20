<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\QuestionDrop;

use Generator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * İçerik ekibinin gönderdiği soru klasörünü okur.
 *
 * Paket "temiz veri" değil, bir ÇALIŞMA KLASÖRÜ: 1652 JSON'un içinde
 * sorular, ilerleme raporları, doğrulama çıktıları ve eski sürümler iç
 * içe duruyor. Bu sınıfın tek işi, aradan yalnızca soruları çekip
 * geri kalanını sessizce geçmek.
 *
 * Karşılaşılan ve burada ele alınan gerçek sorunlar:
 *
 *   · UTF-8 BOM — dosyaların çoğunda var; `json_decode` BOM'u sözdizimi
 *     hatası sayıyor
 *   · İki farklı üst yapı — `{"questions": [...]}` ve düz dizi
 *   · Rapor dosyaları — `{"completed_batches": ...}` gibi, sorusu yok
 *   · 15 okunamayan dosya — UTF-16 ya da bozuk JSON; atlanıyor ama
 *     SAYILIYOR, çünkü sessizce kaybolan 15 dosya fark edilmez
 */
final class DropReader
{
    /** Soru listesinin saklanabileceği anahtarlar. */
    private const LIST_KEYS = ['questions', 'exercises', 'items'];

    /** @var list<array{file: string, reason: string}> */
    private array $skipped = [];

    private int $fileCount = 0;

    /**
     * Klasördeki tüm soruları tek tek verir.
     *
     * `Generator`: 312 MB'lık paketi belleğe almak gereksiz ve bazı
     * makinelerde imkânsız.
     *
     * @return Generator<array{question: array<string, mixed>, file: string}>
     */
    public function read(string $root): Generator
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $this->fileCount++;
            $path = $file->getPathname();
            $relative = ltrim(str_replace($root, '', $path), '/');

            $decoded = $this->decode($path, $relative);

            if ($decoded === null) {
                continue;
            }

            $questions = $this->questionsIn($decoded);

            if ($questions === null) {
                // Rapor ya da ilerleme dosyası — hata değil.
                continue;
            }

            foreach ($questions as $question) {
                if (! is_array($question) || ! isset($question['exercise'])) {
                    continue;
                }

                yield ['question' => $question, 'file' => $relative];
            }
        }
    }

    /** @return array<string, mixed>|list<mixed>|null */
    private function decode(string $path, string $relative): ?array
    {
        $raw = @file_get_contents($path);

        if ($raw === false) {
            $this->skipped[] = ['file' => $relative, 'reason' => 'okunamadı'];

            return null;
        }

        // BOM'u kırp. Dosyaların çoğunda var ve json_decode onu
        // sözdizimi hatası sayıyor.
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        if (! mb_check_encoding($raw, 'UTF-8')) {
            // UTF-16 olabilir; çevirmeyi dene.
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'UTF-16, UTF-16LE, UTF-16BE');

            if (! is_string($converted) || ! mb_check_encoding($converted, 'UTF-8')) {
                $this->skipped[] = ['file' => $relative, 'reason' => 'UTF-8 değil'];

                return null;
            }

            $raw = $converted;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->skipped[] = ['file' => $relative, 'reason' => 'bozuk JSON: '.$e->getMessage()];

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Çözülen dosyanın içindeki soru listesini bulur.
     *
     * @param  array<string, mixed>|list<mixed>  $decoded
     * @return list<mixed>|null
     */
    private function questionsIn(array $decoded): ?array
    {
        // Düz dizi: dosyanın kendisi soru listesi.
        if (array_is_list($decoded)) {
            return $decoded;
        }

        foreach (self::LIST_KEYS as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key]) && array_is_list($decoded[$key])) {
                return $decoded[$key];
            }
        }

        return null;
    }

    public function fileCount(): int
    {
        return $this->fileCount;
    }

    /** @return list<array{file: string, reason: string}> */
    public function skipped(): array
    {
        return $this->skipped;
    }
}
