<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\Application\QuestionDrop\QuestionDeriver;
use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ValidatorRegistry;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Topic;
use App\Shared\Domain\Enum\PublishStatus;
use Illuminate\Console\Command;

/**
 * Veritabanındaki çoktan seçmeli sorulardan başka tipler türetir.
 *
 * Paketten gelen soruların tamamı çoktan seçmeli; ünite şablonları ise
 * eşleştirme, bilgi kartı ve sayısal giriş adımları tanımlıyor. Bu komut
 * olmadan o adımlar boş kalıyor.
 *
 * Türetilen her soru, yazılmadan ÖNCE mevcut doğrulayıcılardan geçiyor:
 * türetme mantığı hatalıysa bozuk soru veritabanına girmiyor, sayılıyor.
 *
 *   php artisan content:tip-turet --deneme
 *   php artisan content:tip-turet
 */
final class DeriveExerciseTypesCommand extends Command
{
    protected $signature = 'content:tip-turet
        {--topic= : Yalnızca bu konu kodu}
        {--yayinla : Taslak yerine yayınlanmış olarak yaz}
        {--deneme : Hiçbir şey yazma, yalnızca raporla}';

    protected $description = 'Çoktan seçmeli sorulardan sayısal giriş, bilgi kartı ve eşleştirme türetir';

    public function handle(QuestionDeriver $deriver, ValidatorRegistry $validators): int
    {
        $dryRun = (bool) $this->option('deneme');
        $status = $this->option('yayinla') ? PublishStatus::Published : PublishStatus::Draft;

        $query = Exercise::query()->where('type', ExerciseType::MultipleChoice->value);

        if (is_string($this->option('topic')) && $this->option('topic') !== '') {
            $topicId = Topic::query()
                ->where('code', $this->option('topic'))
                ->value('id');

            if ($topicId === null) {
                $this->error('Konu bulunamadı: '.$this->option('topic'));

                return self::FAILURE;
            }

            $query->where('topic_id', $topicId);
        }

        $source = $query->count();

        if ($source === 0) {
            $this->warn('Türetilecek çoktan seçmeli soru yok.');

            return self::SUCCESS;
        }

        $this->info($dryRun
            ? "Deneme çalışması — {$source} çoktan seçmeli soru taranıyor."
            : "{$source} çoktan seçmeli sorudan türetiliyor…");

        $made = ['numeric_input' => 0, 'flashcard' => 0, 'matching' => 0];
        $skipped = ['uygun_değil' => 0, 'doğrulama' => 0, 'zaten_vardı' => 0];

        /** @var array<int, list<array{stem: string, answer: string}>> $pairsByTopic */
        $pairsByTopic = [];

        $query->chunkById(500, function ($chunk) use (
            $deriver, $validators, $dryRun, $status, &$made, &$skipped, &$pairsByTopic
        ): void {
            foreach ($chunk as $exercise) {
                $content = (array) $exercise->content;
                $answerKey = (array) $exercise->answer_key;
                $topicId = (int) $exercise->topic_id;

                $produced = false;

                foreach ([
                    $deriver->toNumericInput($content, $answerKey),
                    $deriver->toFlashcard($content, $answerKey, $exercise->explanation),
                ] as $derived) {
                    if ($derived === null) {
                        continue;
                    }

                    $produced = true;
                    $this->persist(
                        $derived, $topicId, $exercise, $validators,
                        $dryRun, $status, $made, $skipped,
                    );
                }

                // Eşleştirme için çiftler biriktiriliyor: tek soru
                // eşleştirme yapamaz, dörde ihtiyaç var.
                $pair = $deriver->pairFor($content, $answerKey);

                if ($pair !== null) {
                    $pairsByTopic[$topicId][] = $pair;
                    $produced = true;
                }

                if (! $produced) {
                    $skipped['uygun_değil']++;
                }
            }
        });

        // Biriken çiftlerden eşleştirme kur.
        foreach ($pairsByTopic as $topicId => $pairs) {
            foreach (array_chunk($pairs, 4) as $group) {
                $derived = $deriver->toMatching($group);

                if ($derived === null) {
                    continue;
                }

                $this->persist(
                    $derived, $topicId, null, $validators,
                    $dryRun, $status, $made, $skipped,
                );
            }
        }

        $this->newLine();

        foreach ($made as $type => $count) {
            $this->components->twoColumnDetail("<fg=green>{$type}</>", (string) $count);
        }

        $this->newLine();

        foreach ($skipped as $reason => $count) {
            $this->components->twoColumnDetail(
                '<fg=gray>'.str_replace('_', ' ', $reason).'</>',
                (string) $count,
            );
        }

        $this->newLine();
        $this->components->twoColumnDetail(
            '<fg=gray>durum</>',
            $dryRun ? 'DENEME (yazılmadı)' : $status->value,
        );

        return self::SUCCESS;
    }

    /**
     * @param  array{type: ExerciseType, content: array<string, mixed>, answer_key: array<string, mixed>}  $derived
     * @param  array<string,int>  $made
     * @param  array<string,int>  $skipped
     */
    private function persist(
        array $derived,
        int $topicId,
        ?Exercise $source,
        ValidatorRegistry $validators,
        bool $dryRun,
        PublishStatus $status,
        array &$made,
        array &$skipped,
    ): void {
        $type = $derived['type'];

        /*
         | Türetilen soru da DOĞRULAMADAN geçiyor.
         |
         | Türetme mantığı hatalıysa (eşleştirmede tekrarlanan cevap,
         | sayısal girişte ayrıştırılamayan değer) bozuk soru sessizce
         | veritabanına girerdi. Burada yakalanıyor ve sayılıyor.
        */
        $result = $validators->for($type)->validate($derived['content'], $derived['answer_key']);

        if (! $result->passes()) {
            $skipped['doğrulama']++;

            return;
        }

        $fingerprint = hash('sha256', $topicId.$type->value.json_encode($derived['content']));
        $uuid = $this->deterministicUuid($fingerprint);

        if ($dryRun) {
            $made[$type->value]++;

            return;
        }

        $model = Exercise::query()->firstOrCreate(
            ['uuid' => $uuid],
            [
                'topic_id' => $topicId,
                'owner_course_id' => null,
                'type' => $type->value,
                'content' => $derived['content'],
                'answer_key' => $derived['answer_key'],
                // Açıklama bilgi kartında zaten arka yüze gömülü;
                // sayısal girişte kaynağınki olduğu gibi taşınıyor.
                'explanation' => $type === ExerciseType::Flashcard ? null : $source?->explanation,
                // Eşleştirmede tek bir kaynak yok (dört sorudan kuruldu);
                // o yüzden varsayılana düşüyor.
                'difficulty' => $source === null ? 3 : $source->difficulty,
                'applicable_scopes' => $source === null ? ['tyt'] : $source->applicable_scopes,
                'status' => $status,
            ],
        );

        $model->wasRecentlyCreated ? $made[$type->value]++ : $skipped['zaten_vardı']++;
    }

    private function deterministicUuid(string $fingerprint): string
    {
        $hex = substr($fingerprint, 0, 32);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4),
            substr($hex, 16, 4), substr($hex, 20, 12),
        );
    }
}
