<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hiçbir node'un seçemediği soruları raporlar.
 *
 * ## `content:validate` ile farkı
 *
 * O komut node'dan havuza bakıyor: "bu node'un yeterli sorusu var mı".
 * Bu komut TERSİ yönden bakıyor: "bu soruya hangi node ulaşabiliyor".
 *
 * İkisi birbirinin yerine geçmiyor. Bir node bol soruyla dolup geçebilir,
 * aynı anda o konuda yüzlerce soru hiçbir node'un filtresine uymadığı
 * için öğrenciye hiç gelmiyor olabilir. İkinci durum sessizdir: soru
 * panelde görünür, istatistiklerde sayılır, "20 bin sorumuz var"
 * denir — ama öğrenci onu asla görmez.
 *
 * ## Üç kategori, üç farklı anlam
 *
 *   · **ulaşılabilir** — en az bir node seçebiliyor.
 *   · **ünite bekliyor** — konuyu henüz hiçbir ünite kullanmıyor.
 *     Bu bir kusur DEĞİL: o konu için ünite açıldığı gün soru
 *     kendiliğinden kullanılabilir oluyor. Ekip paketinden gelen
 *     soruların büyük kısmı burada.
 *   · **erişilmez** — konuyu kullanan ünite VAR ama soru yine
 *     seçilemiyor. Tek gerçek kusur kategorisi bu.
 *
 * Erişilmezlik üç kapıdan birinde oluyor ve her birinin düzeltmesi farklı:
 * tip (hiçbir node bu tipi istemiyor), zorluk (bant dışı), kapsam
 * (sorunun sınavı node'un sınavıyla uyuşmuyor).
 *
 * ## Tekrar node'u her tipi kabul ediyor
 *
 * `ReviewQueueSelector` kuyruk yetersiz kaldığında havuzdan tip ve
 * zorluk filtresi olmadan dolduruyor. Bu hesaba katılmazsa rapor
 * binlerce soruyu haksız yere erişilmez sayıyor — ilk sürümünde
 * tam bunu yaptı ve 1.192 soru yanlış raporlandı.
 */
final class UnreachableExercisesCommand extends Command
{
    protected $signature = 'content:erisilmeyen
        {--detay : Erişilmez soruları konu ve sebep kırılımıyla listele}';

    protected $description = 'Hiçbir node\'un seçemediği soruları raporlar';

    public function handle(): int
    {
        $accept = $this->acceptanceByTopic();

        if ($accept === []) {
            $this->warn('Havuzdan soru çeken node yok; rapor anlamsız.');

            return self::SUCCESS;
        }

        $reachable = 0;
        $waiting = 0;
        $byReason = ['tip' => 0, 'zorluk' => 0, 'kapsam' => 0];
        $detail = [];

        DB::table('exercises')
            ->select('topic_id', 'type', 'difficulty', 'applicable_scopes')
            ->orderBy('id')
            ->chunk(2000, function ($rows) use (&$reachable, &$waiting, &$byReason, &$detail, $accept): void {
                foreach ($rows as $exercise) {
                    $specs = $accept[$exercise->topic_id] ?? null;

                    if ($specs === null) {
                        $waiting++;

                        continue;
                    }

                    $scopes = json_decode((string) $exercise->applicable_scopes, true);

                    $reason = $this->reachability(
                        (string) $exercise->type,
                        (int) $exercise->difficulty,
                        is_array($scopes) ? array_values(array_filter($scopes, 'is_string')) : [],
                        $specs,
                    );

                    if ($reason === null) {
                        $reachable++;

                        continue;
                    }

                    $byReason[$reason]++;

                    $key = $exercise->topic_id.'|'.$exercise->type.'|'.$reason;
                    $detail[$key] = ($detail[$key] ?? 0) + 1;
                }
            });

        $unreachable = array_sum($byReason);

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green>ulaşılabilir</>', (string) $reachable);
        $this->components->twoColumnDetail(
            '<fg=gray>ünite bekliyor</>',
            $waiting.' <fg=gray>(kusur değil — ünite açılınca kullanılır)</>',
        );
        $this->components->twoColumnDetail(
            $unreachable > 0 ? '<fg=yellow>ERİŞİLMEZ</>' : '<fg=green>erişilmez</>',
            (string) $unreachable,
        );

        if ($unreachable === 0) {
            $this->newLine();
            $this->info('Ünitesi olan her konudaki her soru en az bir node tarafından seçilebiliyor.');

            return self::SUCCESS;
        }

        foreach ($byReason as $reason => $count) {
            if ($count > 0) {
                $this->components->twoColumnDetail("  <fg=gray>{$reason}</>", (string) $count);
            }
        }

        $this->newLine();
        $this->line($this->advice($byReason));

        if ($this->option('detay')) {
            $this->detail($detail);
        } else {
            $this->newLine();
            $this->line('Kırılım için: <fg=cyan>--detay</>');
        }

        // Erişilmez soru bir uyarı, çökme değil: içerik ekibi bilinçli
        // olarak başka sınav için soru yazmış olabilir.
        return self::SUCCESS;
    }

    /**
     * Her konu için, o konuyu kullanan node'ların kabul ettiği
     * (tip, zorluk aralığı, kapsam) kümesi.
     *
     * @return array<int, list<array{types: list<string>, min: int, max: int, scope: string|null}>>
     */
    private function acceptanceByTopic(): array
    {
        $byUnit = [];
        $unitScope = [];
        $hasReview = [];

        foreach (DB::table('unit_nodes')->get(['unit_id', 'selection_rule']) as $node) {
            $rule = json_decode((string) $node->selection_rule, true);
            $rule = is_array($rule) ? $rule : [];
            $mode = $rule['mode'] ?? null;

            if ($mode === 'review_queue') {
                $hasReview[$node->unit_id] = true;

                continue;
            }

            // `blueprint` modu derse göre seçiyor, üniteye bağlı değil;
            // bu raporun ünite temelli modeline girmiyor.
            if ($mode !== 'pool') {
                continue;
            }

            $filters = (array) ($rule['filters'] ?? []);
            $scope = is_string($filters['scope'] ?? null) ? $filters['scope'] : null;

            $byUnit[$node->unit_id][] = [
                'types' => array_values(array_filter((array) ($filters['types'] ?? []), 'is_string')),
                'min' => (int) ($filters['difficulty']['min'] ?? 1),
                'max' => (int) ($filters['difficulty']['max'] ?? 5),
                'scope' => $scope,
            ];

            if ($scope !== null) {
                $unitScope[$node->unit_id] = $scope;
            }
        }

        // Tekrar node'u: tip ve zorluk serbest, kapsam yine geçerli.
        foreach (array_keys($hasReview) as $unitId) {
            $byUnit[$unitId][] = [
                'types' => [],
                'min' => 1,
                'max' => 5,
                'scope' => $unitScope[$unitId] ?? null,
            ];
        }

        $accept = [];

        foreach ($byUnit as $unitId => $specs) {
            foreach (DB::table('unit_topics')->where('unit_id', $unitId)->pluck('topic_id') as $topicId) {
                foreach ($specs as $spec) {
                    $accept[(int) $topicId][] = $spec;
                }
            }
        }

        return $accept;
    }

    /**
     * Soru seçilebiliyor mu; değilse HANGİ KAPIDA takıldı.
     *
     * Sebebi ayırmak önemli: tip kusuru şablon değişikliği, zorluk
     * kusuru içerik düzeltmesi, kapsam kusuru müfredat kararı gerektiriyor.
     *
     * @param  list<string>  $scopes
     * @param  list<array{types: list<string>, min: int, max: int, scope: string|null}>  $specs
     * @return 'tip'|'zorluk'|'kapsam'|null
     */
    private function reachability(string $type, int $difficulty, array $scopes, array $specs): ?string
    {
        $typeMatched = false;
        $difficultyMatched = false;

        foreach ($specs as $spec) {
            if ($spec['types'] !== [] && ! in_array($type, $spec['types'], true)) {
                continue;
            }

            $typeMatched = true;

            if ($difficulty < $spec['min'] || $difficulty > $spec['max']) {
                continue;
            }

            $difficultyMatched = true;

            if ($spec['scope'] === null || in_array($spec['scope'], $scopes, true)) {
                return null;
            }
        }

        return match (true) {
            ! $typeMatched => 'tip',
            ! $difficultyMatched => 'zorluk',
            default => 'kapsam',
        };
    }

    /** @param array<string, int> $byReason */
    private function advice(array $byReason): string
    {
        $lines = [];

        if ($byReason['tip'] > 0) {
            $lines[] = '<fg=yellow>tip</>: hiçbir node bu tipi istemiyor → ünite şablonuna o tip için node ekleyin.';
        }

        if ($byReason['zorluk'] > 0) {
            $lines[] = '<fg=yellow>zorluk</>: soru bandın dışında → sorunun zorluğunu ya da node bandını düzeltin.';
        }

        if ($byReason['kapsam'] > 0) {
            $lines[] = '<fg=yellow>kapsam</>: sorunun sınavı node\'un sınavıyla uyuşmuyor → ya o sınav için ünite açın ya da sorunun kapsamını genişletin.';
        }

        return implode(PHP_EOL, $lines);
    }

    /** @param array<string, int> $detail */
    private function detail(array $detail): void
    {
        arsort($detail);

        $topics = DB::table('topics')->pluck('name', 'id');

        $this->newLine();
        $this->line('<fg=gray>konu · tip · sebep · soru</>');

        foreach (array_slice($detail, 0, 30, true) as $key => $count) {
            [$topicId, $type, $reason] = explode('|', $key);

            $this->line(sprintf(
                '  %-30s %-16s %-7s %d',
                mb_substr((string) ($topics[(int) $topicId] ?? '?'), 0, 30),
                $type,
                $reason,
                $count,
            ));
        }

        if (count($detail) > 30) {
            $this->line('  <fg=gray>… ve '.(count($detail) - 30).' satır daha</>');
        }
    }
}
