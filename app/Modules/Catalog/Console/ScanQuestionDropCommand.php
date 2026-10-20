<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\Application\QuestionDrop\DropReader;
use App\Modules\Catalog\Application\QuestionDrop\TopicMatcher;
use Illuminate\Console\Command;

/**
 * İçerik paketini tarar ve gözden geçirilebilir bir eşleme dosyası üretir.
 *
 * ## Neden iki aşama
 *
 * Paketteki 1372 ekip konu kodunun hiçbiri bizim 254 kodumuzla
 * örtüşmüyor; ekip kendi şemasını kullanmış (`TYT-TUR-003`). Tek komutla
 * "hepsini al" demek, bu eşlemeyi körlemesine yapmak demekti.
 *
 * Yanlış eşlenen soru ÇÖKMEZ — sessizce yanlış konuda durur. Öğrencinin
 * konu analizi bozulur, zayıf olduğu konu güçlü görünür ve kimse fark
 * etmez. Bu yüzden eşleme bir dosyaya çıkıyor, insan onaylıyor.
 *
 * ## Kullanım
 *
 *   php artisan content:paket-tara ~/Downloads/bilgin-app-questions
 *
 * Üretilen dosyada her satır `our_code` alanı taşıyor. `exact` güvenli
 * olanlar baştan dolu gelir; `fuzzy` ve `none` olanları insan doldurur.
 * Boş bırakılan satırın soruları aktarılmaz ve raporlanır.
 */
final class ScanQuestionDropCommand extends Command
{
    protected $signature = 'content:paket-tara
        {path : Soru paketinin kök klasörü}
        {--out=storage/app/soru-eslemesi.json : Üretilecek eşleme dosyası}';

    protected $description = 'Soru paketini tarar, konu eşleme taslağı üretir';

    public function handle(DropReader $reader, TopicMatcher $matcher): int
    {
        $path = rtrim($this->argument('path'), '/');

        if (! is_dir($path)) {
            $this->error("Klasör bulunamadı: {$path}");

            return self::FAILURE;
        }

        $this->info('Taranıyor…');

        /** @var array<string, array{names: array<string,int>, count: int, exams: array<string,int>, subjects: array<string,int>}> $topics */
        $topics = [];
        $total = 0;
        $withoutTopic = 0;
        $types = [];

        foreach ($reader->read($path) as ['question' => $q, 'file' => $file]) {
            $total++;

            $exercise = is_array($q['exercise'] ?? null) ? $q['exercise'] : [];
            $types[$exercise['type'] ?? 'bilinmiyor'] = ($types[$exercise['type'] ?? 'bilinmiyor'] ?? 0) + 1;

            $name = is_string($q['topic'] ?? null) ? trim($q['topic']) : '';
            $teamCode = is_string($q['topic_code'] ?? null) ? trim($q['topic_code']) : '';

            if ($name === '' && $teamCode === '') {
                $withoutTopic++;

                continue;
            }

            /*
             | Anahtar EKİP KODU, ad değil.
             |
             | Ölçüldü: 1372 ekip kodu, 3763 ad var. Kod daha KABA bir
             | gruplama — tek bir `AYT-FIZIK-001` kodunun altında 24
             | farklı alt başlık adı geçiyor ("Konuma bağlı kuvvetin
             | yaptığı iş" gibi). Bunlar müfredat konusu değil, soru
             | başlığı.
             |
             | Adla gruplayınca insanın önüne 3763 satır çıkıyordu ve
             | 3267'si beşten az soru taşıyordu. Kodla gruplayınca ilk
             | 200 satır soruların %73'ünü kapsıyor.
            */
            $key = $teamCode !== '' ? $teamCode : 'ad:'.$name;

            $topics[$key] ??= ['names' => [], 'count' => 0, 'exams' => [], 'subjects' => []];
            $topics[$key]['count']++;

            if ($name !== '') {
                $topics[$key]['names'][$name] = ($topics[$key]['names'][$name] ?? 0) + 1;
            }

            $exam = strtolower(explode(' ', $file)[0] ?: '?');
            $topics[$key]['exams'][$exam] = ($topics[$key]['exams'][$exam] ?? 0) + 1;

            if (is_string($q['subject'] ?? null)) {
                $s = $q['subject'];
                $topics[$key]['subjects'][$s] = ($topics[$key]['subjects'][$s] ?? 0) + 1;
            }
        }

        // Soru sayısına göre sıralı: insan yukarıdan aşağı ilerledikçe
        // her satır bir öncekinden az soru etkiliyor ve nerede
        // durabileceğini görebiliyor.
        uasort($topics, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        $rows = [];
        $tally = ['exact' => 0, 'fuzzy' => 0, 'none' => 0];
        $covered = ['exact' => 0, 'fuzzy' => 0, 'none' => 0];

        foreach ($topics as $key => $topic) {
            $subject = $this->dominant($topic['subjects']);

            // En sık geçen ad, grubun etiketi. Diğerleri `other_names`
            // olarak gösteriliyor ki insan grubun içinde ne olduğunu
            // görmeden eşleme yapmasın.
            $label = $this->dominant($topic['names']) ?? $key;
            $guess = $matcher->suggest($label, $subject);

            $tally[$guess['confidence']]++;
            $covered[$guess['confidence']] += $topic['count'];

            $names = $topic['names'];
            arsort($names);

            $rows[] = [
                'team_code' => $key,
                'topic' => $label,
                'other_names' => array_slice(array_keys($names), 1, 3),
                'questions' => $topic['count'],
                'exam' => $this->dominant($topic['exams']),
                'drop_subject' => $subject,
                'suggested' => $guess['code'],
                'confidence' => $guess['confidence'],
                'score' => $guess['score'],
                // İnsanın dolduracağı alan. `exact` olanlar baştan dolu.
                'our_code' => $guess['confidence'] === 'exact' ? $guess['code'] : null,
            ];
        }

        $out = (string) $this->option('out');
        @mkdir(dirname($out), 0755, true);

        file_put_contents($out, json_encode([
            '_note' => 'ESLEME TASLAGI. "our_code" alanlarini doldurun; '
                .'bos birakilan satirin sorulari AKTARILMAZ. '
                .'"confidence":"exact" satirlar baston dolu gelir.',
            'scanned_at' => now()->toIso8601String(),
            'files' => $reader->fileCount(),
            'questions' => $total,
            'topics' => $rows,
            'unreadable_files' => $reader->skipped(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '');

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Okunan dosya</>', (string) $reader->fileCount());
        $this->components->twoColumnDetail('<fg=gray>Okunamayan</>', (string) count($reader->skipped()));
        $this->components->twoColumnDetail('<fg=gray>Soru</>', (string) $total);
        $this->components->twoColumnDetail('<fg=gray>Konusuz soru</>', (string) $withoutTopic);
        $this->newLine();

        $this->table(
            ['Güven', 'Konu', 'Soru', 'Oran'],
            collect(['exact' => 'birebir', 'fuzzy' => 'benzer', 'none' => 'bulunamadı'])
                ->map(fn (string $label, string $key): array => [
                    $label,
                    (string) $tally[$key],
                    (string) $covered[$key],
                    $total > 0 ? round($covered[$key] / $total * 100).'%' : '0%',
                ])->values()->all(),
        );

        $this->newLine();
        $this->table(
            ['Soru tipi', 'Adet'],
            collect($types)->map(fn (int $n, string $t): array => [$t, (string) $n])->values()->all(),
        );

        $this->newLine();
        $this->info("Eşleme taslağı: {$out}");
        $this->line('Doldurduktan sonra: <fg=cyan>php artisan content:paket-aktar</> ile aktarın.');

        return self::SUCCESS;
    }

    /** @param array<string,int> $counts */
    private function dominant(array $counts): ?string
    {
        if ($counts === []) {
            return null;
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }
}
