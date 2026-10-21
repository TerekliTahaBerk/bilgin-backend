<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\QuestionDrop;

use Generator;
use JsonException;

/**
 * Paketlenmiş soru arşivini satır satır okur.
 *
 * `content:paket-paketle` tarafından üretilen gzip'li NDJSON dosyasını
 * okur. Her satır bir soru ve ARTIK BİZİM ŞEMAMIZDA: konu kodu çözülmüş,
 * doğrulamadan geçmiş, tekrarları ayıklanmış.
 *
 * ## Neden ayrı bir okuyucu
 *
 * {@see DropReader} ekibin 308 MB'lık karışık klasörünü okuyor: BOM'lu
 * dosyalar, UTF-16 kodlama, iki ayrı JSON yapısı, araya karışmış rapor
 * dosyaları. Bunların hiçbiri sunucuda yok ve olmasına gerek de yok.
 *
 * Arşiv formatı bilerek dar: alan adları tek harf (`t`, `y`, `d`, `s`,
 * `c`, `a`, `e`) çünkü 16 bin satırda alan adları tek başına yaklaşık
 * 1 MB yer tutuyordu.
 *
 * ## Akış belleği
 *
 * `gzgets` ile satır satır okunuyor, dosya belleğe ALINMIYOR: açılmış
 * hâli 15,9 MB ve 512 MB bellek sınırı olan bir sunucuda tek seferde
 * `json_decode` etmek riskli olurdu.
 */
final class ArchiveReader
{
    /** @var list<string> */
    private array $skipped = [];

    private int $lines = 0;

    /**
     * @return Generator<int, array{
     *     topic_code: string,
     *     type: string,
     *     content: array<string, mixed>,
     *     answer_key: array<string, mixed>,
     *     explanation: string|null,
     *     difficulty: int,
     *     scopes: list<string>,
     * }>
     */
    public function read(string $file): Generator
    {
        if (! is_file($file) || ! is_readable($file)) {
            $this->skipped[] = "{$file}: okunamıyor";

            return;
        }

        $handle = gzopen($file, 'rb');

        if ($handle === false) {
            $this->skipped[] = "{$file}: açılamadı";

            return;
        }

        while (($line = gzgets($handle)) !== false) {
            $this->lines++;

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                // Tek bozuk satır tüm aktarmayı durdurmamalı; hangi
                // satır olduğu raporlanıyor ki arşiv yeniden üretilebilsin.
                $this->skipped[] = "satır {$this->lines}: {$e->getMessage()}";

                continue;
            }

            if (! is_array($row) || ! is_string($row['t'] ?? null) || ! is_string($row['y'] ?? null)) {
                $this->skipped[] = "satır {$this->lines}: eksik alan";

                continue;
            }

            yield [
                'topic_code' => $row['t'],
                'type' => $row['y'],
                'content' => (array) ($row['c'] ?? []),
                'answer_key' => (array) ($row['a'] ?? []),
                'explanation' => is_string($row['e'] ?? null) ? $row['e'] : null,
                'difficulty' => (int) ($row['d'] ?? 3),
                'scopes' => array_values(array_filter(
                    (array) ($row['s'] ?? ['tyt']),
                    static fn ($v): bool => is_string($v),
                )),
            ];
        }

        gzclose($handle);
    }

    public function lineCount(): int
    {
        return $this->lines;
    }

    /** @return list<string> */
    public function skipped(): array
    {
        return $this->skipped;
    }
}
