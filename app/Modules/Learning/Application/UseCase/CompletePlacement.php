<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\UseCase;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Unit;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Modules\Learning\Domain\Exception\LearningException;
use App\Modules\Learning\Domain\Grading\ExerciseContent;
use App\Modules\Learning\Domain\Grading\GraderRegistry;
use App\Modules\Learning\Domain\Grading\SubmittedAnswer;
use App\Modules\Learning\Infrastructure\Eloquent\Model\PlacementAttempt;
use App\Modules\Learning\Infrastructure\Eloquent\Model\UserNodeProgress;
use App\Shared\Clock\ClockInterface;
use App\Shared\Domain\Learner\PlacementRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Seviye testini puanlar ve öğrenciyi yoluna yerleştirir.
 *
 * Yerleştirme, node'ları `completed` işaretleyerek yapılıyor — yeni bir
 * kilit kuralı yazmaya gerek yok, çünkü var olan `PreviousNodeCompleted`
 * kuralı zaten tam olarak bunu okuyor. Eklenen tek şey `placed_out_at`
 * damgası: ünite yüzdesi ve rozetler, oynanmamış turu oynanmış saymasın.
 *
 * Eşikler bilerek CÖMERT DEĞİL. Yanlış yere yerleştirilen bir öğrenci,
 * temelini atlayıp dört adım sonra duvara çarpar ve bunu kendi
 * başarısızlığı sanır. Şüphede kalınan her durumda daha geriden başlatmak
 * doğru olan.
 */
final readonly class CompletePlacement
{
    /** Dersi tamamen atlatacak doğruluk — ilk ünitenin çalışma adımları. */
    private const STRONG = 80;

    /** Yalnızca ilk adımı atlatacak doğruluk. */
    private const FAMILIAR = 60;

    public function __construct(
        private ClockInterface $clock,
        private GraderRegistry $graders,
        private PlacementRecorder $placement,
    ) {}

    /**
     * @param  array<int|string, array<string, mixed>>  $answers  exercise_id => cevap
     * @return array<string, mixed> sonuç özeti
     */
    public function __invoke(int $userId, int $attemptId, array $answers): array
    {
        $attempt = PlacementAttempt::query()
            ->where('user_id', $userId)
            ->findOrFail($attemptId);

        if (! $attempt->isOpen()) {
            throw LearningException::placementClosed();
        }

        [$correct, $perCourse] = $this->grade($attempt, $answers);

        return DB::transaction(function () use ($attempt, $userId, $correct, $perCourse): array {
            $placements = [];

            foreach ($perCourse as $courseId => $tally) {
                $accuracy = $tally['asked'] === 0
                    ? 0
                    : (int) round($tally['correct'] / $tally['asked'] * 100);

                $unlocked = $this->placeInCourse($userId, (int) $courseId, $accuracy);

                $placements[] = [
                    'course_id' => (int) $courseId,
                    'course_name' => $tally['name'],
                    'asked' => $tally['asked'],
                    'correct' => $tally['correct'],
                    'accuracy' => $accuracy,
                    'skipped_nodes' => count($unlocked),
                    'level' => $this->levelLabel($accuracy),
                ];
            }

            $result = [
                'correct' => $correct,
                'total' => $attempt->question_count,
                'courses' => $placements,
            ];

            $attempt->update([
                'status' => 'completed',
                'correct_count' => $correct,
                'result' => $result,
                'completed_at' => $this->clock->now(),
            ]);

            $this->placement->markCompleted($userId);

            return $result;
        });
    }

    /**
     * Testi çözmeden geçer.
     *
     * Atlamak bir başarısızlık değil ve öyle kaydedilmiyor: hiçbir node
     * açılmıyor, hiçbir ders "zayıf" işaretlenmiyor. Öğrenci baştan
     * başlıyor — zaten varsayılan olan yer.
     */
    public function skip(int $userId): void
    {
        DB::transaction(function () use ($userId): void {
            PlacementAttempt::query()
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->update([
                    'status' => 'skipped',
                    'completed_at' => $this->clock->now(),
                ]);

            $this->placement->markCompleted($userId);
        });
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $answers
     * @return array{0: int, 1: array<int, array{name: string, asked: int, correct: int}>}
     */
    private function grade(PlacementAttempt $attempt, array $answers): array
    {
        $correct = 0;
        $perCourse = [];

        foreach ($attempt->questions as $question) {
            $courseId = (int) $question['course_id'];

            $perCourse[$courseId] ??= [
                'name' => (string) ($question['course_name'] ?? 'Ders'),
                'asked' => 0,
                'correct' => 0,
            ];
            $perCourse[$courseId]['asked']++;

            $given = $answers[$question['exercise_id']] ?? null;

            // Cevaplanmamış soru yanlış sayılıyor, atlanmıyor: boş bırakmak
            // da bir bilgi ve testin uzunluğu sabit kalmalı.
            if (! is_array($given)) {
                continue;
            }

            $grader = $this->graders->for(ExerciseType::from((string) $question['type']));

            $verdict = $grader->grade(
                new ExerciseContent(
                    content: $question['content'],
                    answerKey: $question['answer_key'],
                ),
                new SubmittedAnswer($given),
            );

            // Kısmi puan burada TAMAMA yuvarlanmıyor: seviye testi "biliyor
            // mu" sorusunu soruyor, "ne kadar biliyor"u değil.
            if ($verdict->isCorrect) {
                $correct++;
                $perCourse[$courseId]['correct']++;
            }
        }

        return [$correct, $perCourse];
    }

    /**
     * Dersin ilk ünitesinde kaç adımın atlanacağını belirler.
     *
     * Yalnızca İLK ünite atlanıyor, ders boyunca değil: 12 soruluk bir test
     * bir dersin tamamı hakkında konuşamaz. İkinci üniteden sonrası yine
     * oynanarak açılıyor.
     *
     * @return list<int> açılan node kimlikleri
     */
    private function placeInCourse(int $userId, int $courseId, int $accuracy): array
    {
        if ($accuracy < self::FAMILIAR) {
            return [];
        }

        $unit = Unit::query()
            ->where('course_id', $courseId)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->first();

        if ($unit === null) {
            return [];
        }

        $nodes = UnitNode::query()
            ->where('unit_id', $unit->id)
            ->orderBy('sort_order')
            ->get();

        /*
         | Ünite sınavı ve hızlı tekrar HİÇBİR ZAMAN atlanmıyor.
         |
         | Sınav, öğrencinin kendi seviyesini kendi görmesi için duruyor;
         | onu da atlatmak, seviye testinin sonucunu sorgulanamaz kılardı.
         | Hızlı tekrar ise aralıklı tekrar kuyruğunu besliyor — atlanırsa
         | kuyruk boş başlar.
         */
        $skippable = $nodes
            ->filter(static fn (UnitNode $n): bool => ! in_array(
                $n->node_type->value,
                ['unit_challenge', 'quick_review'],
                true,
            ))
            ->values();

        $take = $accuracy >= self::STRONG
            ? $skippable->count()
            : 1;

        $unlocked = [];
        $now = $this->clock->now();

        foreach ($skippable->take($take) as $node) {
            /*
             | `updateOrCreate` ve `attempts` artırılmıyor: bu satır bir
             | DENEME değil, bir muafiyet. Daha önce oynanmış bir node'un
             | gerçek sonucunu da ezmiyor — öğrenci testten önce oynamış
             | olabilir ve o skor bundan daha doğrudur.
             */
            $existing = UserNodeProgress::query()
                ->where('user_id', $userId)
                ->where('unit_node_id', $node->id)
                ->first();

            if ($existing?->completed === true) {
                continue;
            }

            UserNodeProgress::query()->updateOrCreate(
                ['user_id' => $userId, 'unit_node_id' => $node->id],
                [
                    'unit_id' => $unit->id,
                    'course_id' => $courseId,
                    'completed' => true,
                    'best_accuracy' => $accuracy,
                    'completed_at' => $now,
                    'placed_out_at' => $now,
                ],
            );

            $unlocked[] = $node->id;
        }

        return $unlocked;
    }

    private function levelLabel(int $accuracy): string
    {
        return match (true) {
            $accuracy >= self::STRONG => 'guclu',
            $accuracy >= self::FAMILIAR => 'tanidik',
            default => 'bastan',
        };
    }
}
