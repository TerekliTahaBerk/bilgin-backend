<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\Application\QuestionDrop\ArchiveReader;
use App\Modules\Catalog\Application\QuestionDrop\DropReader;
use App\Modules\Catalog\Application\QuestionDrop\TopicMatcher;
use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ValidatorRegistry;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use App\Shared\Domain\Enum\PublishStatus;
use Generator;
use Illuminate\Console\Command;
use JsonException;

/**
 * Soru paketini veritabanına aktarır.
 *
 * ## İki kaynak
 *
 * Komut hem ekibin ham klasörünü hem paketlenmiş arşivi kabul ediyor:
 *
 *   · **klasör** — yerelde çalışır. 308 MB, 1652 dosya, karışık kodlama.
 *     `--map` ile onaylanmış eşleme dosyası gerekiyor.
 *   · **arşiv**  — `.ndjson.gz`. Sunucuda çalışır. 3,6 MB, konu kodları
 *     çözülmüş, eşleme dosyasına ihtiyaç duymuyor.
 *
 * Arşiv {@see PackageQuestionDropCommand} ile üretiliyor ve depoya
 * giriyor; böylece sunucunun ham klasöre hiç ihtiyacı olmuyor.
 *
 * ## Ünite gerekmiyor
 *
 * Sorular ünitelere değil KONULARA bağlanıyor. `unit_topics` üniteleri
 * konulara bağlıyor ve soru seçici havuzu oradan çekiyor; yani bir
 * konuya soru eklendiği anda o konuyu kullanan tüm adımların havuzu
 * büyüyor. 19 bin soru için ünite icat etmeye gerek yok.
 *
 * Henüz hiçbir ünitenin kullanmadığı konulara düşen sorular diskte
 * beklemiyor — veritabanında duruyor ve o konu için ünite açıldığı gün
 * kendiliğinden kullanılabilir oluyor.
 *
 * ## Taslak olarak giriyorlar
 *
 * Paketteki her sorunun `review_status` alanı `pending_editorial_review`.
 * Yayına almak editörün kararı; `--yayinla` bayrağı bunu bilinçli bir
 * tercih hâline getiriyor, varsayılan değil.
 */
final class IngestQuestionDropCommand extends Command
{
    protected $signature = 'content:paket-aktar
        {path : Soru paketi klasörü ya da paketlenmiş .ndjson.gz arşivi}
        {--map=database/question-drop/topic-map.json : Onaylanmış eşleme (yalnızca klasör için)}
        {--yayinla : Taslak yerine yayınlanmış olarak aktar}
        {--deneme : Hiçbir şey yazma, yalnızca raporla}';

    protected $description = 'Soru paketini ya da paketlenmiş arşivi içe aktarır';

    /** @var array<string, int> */
    private array $stats = [
        'okunan' => 0, 'yazılan' => 0, 'zaten_vardı' => 0,
        'eşleme_yok' => 0, 'tip_bilinmiyor' => 0, 'geçersiz' => 0,
    ];

    /** @var array<string, int> */
    private array $unmapped = [];

    public function handle(
        DropReader $reader,
        ArchiveReader $archive,
        TopicMatcher $matcher,
        ValidatorRegistry $validators,
    ): int {
        $path = rtrim($this->argument('path'), '/');
        $isArchive = is_file($path) && str_ends_with($path, '.gz');

        if (! $isArchive && ! is_dir($path)) {
            $this->error("Bulunamadı: {$path}");
            $this->line('Klasör ya da <fg=cyan>.ndjson.gz</> arşivi bekleniyor.');

            return self::FAILURE;
        }

        if ($isArchive) {
            $rows = $this->fromArchive($archive, $path, $matcher);
            $this->components->twoColumnDetail('<fg=gray>Kaynak</>', 'arşiv — '.basename($path));
        } else {
            $map = $this->loadMap($matcher);

            if ($map === null) {
                return self::FAILURE;
            }

            $rows = $this->fromDrop($reader, $path, $map);
            $this->components->twoColumnDetail('<fg=gray>Kaynak</>', 'klasör — '.basename($path));
        }

        $dryRun = (bool) $this->option('deneme');
        $status = $this->option('yayinla') ? PublishStatus::Published : PublishStatus::Draft;

        $this->info($dryRun ? 'Deneme çalışması — hiçbir şey yazılmayacak.' : 'Aktarılıyor…');

        $invalidSamples = [];

        // Parmak izleri bellekte: aynı soru paketin birden çok dosyasında
        // geçiyor (4.677 tekrar eden gövde ölçüldü) ve her birini
        // veritabanına sormak 19 bin gereksiz sorgu olurdu.
        $seen = [];

        foreach ($rows as $row) {
            /*
             | Doğrulama HER İKİ kaynak için de çalışıyor.
             |
             | Arşivdeki satırlar paketlenirken doğrulanmış olsa da arşiv
             | depoda duruyor ve doğrulama kuralları ondan sonra
             | değişebilir. Burada tekrar bakmak saf PHP, ucuz; atlamak
             | ise eski bir arşivin yeni kuralları sessizce ihlal etmesi
             | demek olurdu.
            */
            $result = $validators->for($row['type'])->validate($row['content'], $row['answer_key']);

            if (! $result->passes()) {
                $this->stats['geçersiz']++;

                if (count($invalidSamples) < 5) {
                    $invalidSamples[] = sprintf(
                        '%s — %s',
                        $row['label'],
                        implode('; ', $result->errors),
                    );
                }

                continue;
            }

            $fingerprint = hash(
                'sha256',
                $row['topic_id'].$row['type']->value.json_encode($row['content']),
            );

            if (isset($seen[$fingerprint])) {
                $this->stats['zaten_vardı']++;

                continue;
            }

            $seen[$fingerprint] = true;

            if ($dryRun) {
                $this->stats['yazılan']++;

                continue;
            }

            $model = Exercise::query()->firstOrCreate(
                ['uuid' => $this->deterministicUuid($fingerprint)],
                [
                    'topic_id' => $row['topic_id'],
                    /*
                     | `owner_course_id` BİLEREK boş.
                     |
                     | Alan yalnızca sahiplik/yazım içindir, seçim ona
                     | bakmıyor. Paketteki bir soruyu hangi derse ait
                     | sayacağımızı tahmin etmek (aynı konu birden çok
                     | derste geçebiliyor) yanlış bir sahiplik kaydı
                     | bırakırdı; boş bırakmak dürüst olanı.
                    */
                    'owner_course_id' => null,
                    'type' => $row['type']->value,
                    'content' => $row['content'],
                    'answer_key' => $row['answer_key'],
                    'explanation' => $row['explanation'],
                    'difficulty' => $row['difficulty'],
                    'applicable_scopes' => $row['scopes'],
                    'status' => $status,
                ],
            );

            $model->wasRecentlyCreated ? $this->stats['yazılan']++ : $this->stats['zaten_vardı']++;
        }

        /*
         | İki okuyucu atlananları farklı biçimde tutuyor: ham klasör
         | dosya adı + sebep, arşiv ise tek satırlık mesaj. Raporda
         | ikisi de aynı listede görünsün diye burada tek biçime
         | indiriliyor — sebebi göstermek sayıyı göstermekten önemli,
         | çünkü arşivde atlanan bir satır dosyanın bozulduğu anlamına
         | gelir ve sunucuda yerine bakılacak başka kaynak yok.
        */
        $unreadable = $isArchive
            ? $archive->skipped()
            : array_map(
                static fn (array $s): string => $s['file'].': '.$s['reason'],
                $reader->skipped(),
            );

        $this->report($invalidSamples, $unreadable, $status, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Paketlenmiş arşivden normalize satırlar.
     *
     * @return Generator<int, array{topic_id: int, type: ExerciseType, content: array<string, mixed>, answer_key: array<string, mixed>, explanation: string|null, difficulty: int, scopes: list<string>, label: string}>
     */
    private function fromArchive(ArchiveReader $archive, string $file, TopicMatcher $matcher): Generator
    {
        // Konu kodu → kimlik önbelleği: arşivde 424 ayrı kod var ama
        // 16 bin satır; her satırda veritabanına sormak gereksiz.
        $ids = [];

        foreach ($archive->read($file) as $row) {
            $this->stats['okunan']++;

            $code = $row['topic_code'];

            if (! array_key_exists($code, $ids)) {
                $ids[$code] = $matcher->idFor($code);
            }

            $topicId = $ids[$code];

            if ($topicId === null) {
                /*
                 | Arşiv depoda, konu taksonomisi ise göçlerde duruyor.
                 | Biri diğerinden eski kalabilir: arşiv paketlendikten
                 | sonra bir konu yeniden adlandırılırsa kodu tutmaz.
                 | Sessiz geçmek yüzlerce sorunun kaybolması olurdu.
                */
                $this->stats['eşleme_yok']++;
                $this->unmapped[$code] = ($this->unmapped[$code] ?? 0) + 1;

                continue;
            }

            $type = ExerciseType::tryFrom($row['type']);

            if ($type === null) {
                $this->stats['tip_bilinmiyor']++;

                continue;
            }

            yield [
                'topic_id' => $topicId,
                'type' => $type,
                'content' => $row['content'],
                'answer_key' => $row['answer_key'],
                'explanation' => $row['explanation'],
                'difficulty' => $row['difficulty'],
                'scopes' => $row['scopes'] !== [] ? $row['scopes'] : ['tyt'],
                'label' => $code,
            ];
        }
    }

    /**
     * Ham klasörden normalize satırlar.
     *
     * @param  array<string, int>  $map
     * @return Generator<int, array{topic_id: int, type: ExerciseType, content: array<string, mixed>, answer_key: array<string, mixed>, explanation: string|null, difficulty: int, scopes: list<string>, label: string}>
     */
    private function fromDrop(DropReader $reader, string $path, array $map): Generator
    {
        foreach ($reader->read($path) as ['question' => $q]) {
            $this->stats['okunan']++;

            $teamCode = is_string($q['topic_code'] ?? null) ? trim($q['topic_code']) : '';
            $name = is_string($q['topic'] ?? null) ? trim($q['topic']) : '';
            $key = $teamCode !== '' ? $teamCode : 'ad:'.$name;

            $topicId = $map[$key] ?? null;

            if ($topicId === null) {
                $this->stats['eşleme_yok']++;
                $label = $name !== '' ? $name : $key;
                $this->unmapped[$label] = ($this->unmapped[$label] ?? 0) + 1;

                continue;
            }

            $exercise = is_array($q['exercise'] ?? null) ? $q['exercise'] : [];
            $type = ExerciseType::tryFrom((string) ($exercise['type'] ?? ''));

            if ($type === null) {
                $this->stats['tip_bilinmiyor']++;

                continue;
            }

            $explanation = $exercise['explanation'] ?? null;

            yield [
                'topic_id' => $topicId,
                'type' => $type,
                'content' => (array) ($exercise['content'] ?? []),
                'answer_key' => (array) ($exercise['answer_key'] ?? []),
                'explanation' => is_string($explanation) ? $explanation : null,
                'difficulty' => (int) ($exercise['difficulty'] ?? 3),
                'scopes' => array_values(array_filter(
                    (array) ($exercise['applicable_scopes'] ?? ['tyt']),
                    static fn ($v): bool => is_string($v),
                )) ?: ['tyt'],
                'label' => (string) ($q['question_id'] ?? '(kimliksiz)'),
            ];
        }
    }

    /**
     * Eşleme dosyasını `ekip kodu => topic_id` hâline getirir.
     *
     * @return array<string, int>|null
     */
    private function loadMap(TopicMatcher $matcher): ?array
    {
        $file = (string) $this->option('map');

        if (! is_file($file)) {
            $this->error("Eşleme dosyası yok: {$file}");
            $this->line('Önce <fg=cyan>php artisan content:paket-tara</> çalıştırın.');

            return null;
        }

        try {
            $decoded = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error("Eşleme dosyası okunamadı: {$e->getMessage()}");

            return null;
        }

        $map = [];
        $unknown = [];

        foreach ((array) ($decoded['topics'] ?? []) as $row) {
            $ourCode = $row['our_code'] ?? null;

            if (! is_string($ourCode) || $ourCode === '') {
                continue;
            }

            $topicId = $matcher->idFor($ourCode);

            if ($topicId === null) {
                // Elle yazılmış bir kod tutmuyorsa SESSİZ GEÇİLMEZ:
                // yazım hatası yüzünden yüzlerce soru kaybolabilir.
                $unknown[$ourCode] = true;

                continue;
            }

            $map[(string) $row['team_code']] = $topicId;
        }

        if ($unknown !== []) {
            $this->warn('Eşleme dosyasında TANINMAYAN konu kodu var; o satırlar atlanacak:');

            foreach (array_keys($unknown) as $code) {
                $this->line("  · {$code}");
            }

            $this->newLine();
        }

        if ($map === []) {
            $this->error('Eşleme dosyasında doldurulmuş satır yok.');

            return null;
        }

        $this->components->twoColumnDetail('<fg=gray>Onaylı eşleme</>', (string) count($map).' konu');

        return $map;
    }

    /**
     * @param  list<string>  $invalid
     * @param  list<string>  $unreadable
     */
    private function report(
        array $invalid,
        array $unreadable,
        PublishStatus $status,
        bool $dryRun,
    ): void {
        $this->newLine();

        foreach ($this->stats as $label => $value) {
            $this->components->twoColumnDetail(
                '<fg=gray>'.str_replace('_', ' ', $label).'</>',
                (string) $value,
            );
        }

        if ($unreadable !== []) {
            $this->components->twoColumnDetail(
                '<fg=yellow>okunamayan</>',
                (string) count($unreadable),
            );

            foreach (array_slice($unreadable, 0, 5) as $line) {
                $this->line("  <fg=yellow>·</> {$line}");
            }
        }

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=gray>durum</>',
            $dryRun ? 'DENEME (yazılmadı)' : $status->value,
        );

        if ($invalid !== []) {
            $this->newLine();
            $this->warn('Doğrulamadan geçemeyen sorulardan örnekler:');

            foreach ($invalid as $line) {
                $this->line("  · {$line}");
            }
        }

        if ($this->unmapped !== []) {
            arsort($this->unmapped);
            $this->newLine();
            $this->warn('Eşlemesi olmadığı için ATLANAN konular (en çok soru taşıyan 10):');

            foreach (array_slice($this->unmapped, 0, 10, true) as $name => $count) {
                $this->line(sprintf('  · %-46s %d soru', mb_substr((string) $name, 0, 46), $count));
            }
        }
    }

    /** Parmak izinden kararlı UUID: aynı soru ikinci kez yazılmaz. */
    private function deterministicUuid(string $fingerprint): string
    {
        $hex = substr($fingerprint, 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
