<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\Application\QuestionDrop\DropReader;
use App\Modules\Catalog\Application\QuestionDrop\TopicMatcher;
use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ValidatorRegistry;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use App\Shared\Domain\Enum\PublishStatus;
use Illuminate\Console\Command;
use JsonException;

/**
 * Onaylanmış eşlemeyle soru paketini veritabanına aktarır.
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
        {path : Soru paketinin kök klasörü}
        {--map=storage/app/soru-eslemesi.json : Onaylanmış eşleme dosyası}
        {--yayinla : Taslak yerine yayınlanmış olarak aktar}
        {--deneme : Hiçbir şey yazma, yalnızca raporla}';

    protected $description = 'Soru paketini eşleme dosyasına göre içe aktarır';

    public function handle(
        DropReader $reader,
        TopicMatcher $matcher,
        ValidatorRegistry $validators,
    ): int {
        $path = rtrim($this->argument('path'), '/');

        if (! is_dir($path)) {
            $this->error("Klasör bulunamadı: {$path}");

            return self::FAILURE;
        }

        $map = $this->loadMap($matcher);

        if ($map === null) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('deneme');
        $status = $this->option('yayinla') ? PublishStatus::Published : PublishStatus::Draft;

        $this->info($dryRun ? 'Deneme çalışması — hiçbir şey yazılmayacak.' : 'Aktarılıyor…');

        $stats = [
            'okunan' => 0, 'yazılan' => 0, 'zaten_vardı' => 0,
            'eşleme_yok' => 0, 'tip_bilinmiyor' => 0, 'geçersiz' => 0,
        ];

        /** @var array<string, int> $unmappedTally */
        $unmappedTally = [];
        $invalidSamples = [];

        // Parmak izleri bellekte: aynı soru paketin birden çok dosyasında
        // geçiyor (4.677 tekrar eden gövde ölçüldü) ve her birini
        // veritabanına sormak 19 bin gereksiz sorgu olurdu.
        $seen = [];

        foreach ($reader->read($path) as ['question' => $q]) {
            $stats['okunan']++;

            $teamCode = is_string($q['topic_code'] ?? null) ? trim($q['topic_code']) : '';
            $name = is_string($q['topic'] ?? null) ? trim($q['topic']) : '';
            $key = $teamCode !== '' ? $teamCode : 'ad:'.$name;

            $topicId = $map[$key] ?? null;

            if ($topicId === null) {
                $stats['eşleme_yok']++;
                $label = $name !== '' ? $name : $key;
                $unmappedTally[$label] = ($unmappedTally[$label] ?? 0) + 1;

                continue;
            }

            $exercise = is_array($q['exercise'] ?? null) ? $q['exercise'] : [];
            $type = ExerciseType::tryFrom((string) ($exercise['type'] ?? ''));

            if ($type === null) {
                $stats['tip_bilinmiyor']++;

                continue;
            }

            $content = (array) ($exercise['content'] ?? []);
            $answerKey = (array) ($exercise['answer_key'] ?? []);

            // Mevcut doğrulayıcılar KULLANILIYOR: panelden girilen soruya
            // uygulanan kuralın aynısı. İkinci bir kural seti yazmak,
            // zamanla ayrışan iki kalite ölçütü demekti.
            $result = $validators->for($type)->validate($content, $answerKey);

            if (! $result->passes()) {
                $stats['geçersiz']++;

                if (count($invalidSamples) < 5) {
                    $invalidSamples[] = sprintf(
                        '%s — %s',
                        $q['question_id'] ?? '(kimliksiz)',
                        implode('; ', $result->errors),
                    );
                }

                continue;
            }

            $fingerprint = hash('sha256', $topicId.$type->value.json_encode($content));

            if (isset($seen[$fingerprint])) {
                $stats['zaten_vardı']++;

                continue;
            }

            $seen[$fingerprint] = true;

            if ($dryRun) {
                $stats['yazılan']++;

                continue;
            }

            $model = Exercise::query()->firstOrCreate(
                ['uuid' => $this->deterministicUuid($fingerprint)],
                [
                    'topic_id' => $topicId,
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
                    'type' => $type->value,
                    'content' => $content,
                    'answer_key' => $answerKey,
                    'explanation' => $exercise['explanation'] ?? null,
                    'difficulty' => (int) ($exercise['difficulty'] ?? 3),
                    // Paket `applicable_scopes` diyor, bizim şema aynı adı
                    // kullanıyor; yine de eksikse boş bırakmak yerine
                    // dosyadan gelen sınav kökünden türetmek yanlış olurdu.
                    'applicable_scopes' => (array) ($exercise['applicable_scopes'] ?? ['tyt']),
                    'status' => $status,
                ],
            );

            $model->wasRecentlyCreated ? $stats['yazılan']++ : $stats['zaten_vardı']++;
        }

        $this->report($stats, $unmappedTally, $invalidSamples, $reader, $status, $dryRun);

        return self::SUCCESS;
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
     * @param  array<string,int>  $stats
     * @param  array<string,int>  $unmapped
     * @param  list<string>  $invalid
     */
    private function report(
        array $stats,
        array $unmapped,
        array $invalid,
        DropReader $reader,
        PublishStatus $status,
        bool $dryRun,
    ): void {
        $this->newLine();

        foreach ($stats as $label => $value) {
            $this->components->twoColumnDetail(
                '<fg=gray>'.str_replace('_', ' ', $label).'</>',
                (string) $value,
            );
        }

        if ($reader->skipped() !== []) {
            $this->components->twoColumnDetail(
                '<fg=yellow>okunamayan dosya</>',
                (string) count($reader->skipped()),
            );
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

        if ($unmapped !== []) {
            arsort($unmapped);
            $this->newLine();
            $this->warn('Eşlemesi olmadığı için ATLANAN konular (en çok soru taşıyan 10):');

            foreach (array_slice($unmapped, 0, 10, true) as $name => $count) {
                $this->line(sprintf('  · %-46s %d soru', mb_substr((string) $name, 0, 46), $count));
            }

            $this->newLine();
            $this->line('Bu satırların <fg=cyan>our_code</> alanını doldurup komutu tekrar çalıştırın.');
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
