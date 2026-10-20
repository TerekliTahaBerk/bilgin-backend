<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\UseCase;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use App\Modules\Curriculum\Domain\Contract\VariantCourseReader;
use App\Modules\Learning\Domain\Exception\LearningException;
use App\Modules\Learning\Infrastructure\Eloquent\Model\PlacementAttempt;
use App\Shared\Clock\ClockInterface;
use App\Shared\Domain\Learner\LearnerProfileReader;
use Illuminate\Support\Facades\DB;

/**
 * Seviye belirleme testini başlatır.
 *
 * Amaç öğrenciyi ÖLÇMEK değil, nereden başlayacağını bulmak. Bu ayrım
 * tasarımın her yerinde görünüyor:
 *
 *   · 12 soru, 15 değil — sıfırıncı günde bırakma oranı her soruyla artar
 *   · yalnızca HIZLI tipler — eşleştirme ve sıralama burada süre yer,
 *     bilgi ölçmez; ikisi de gerçek turlarda zaten var
 *   · can harcanmaz, XP verilmez — henüz oyuna girmedi
 *   · her dersten en az iki soru — tek derse yığılan bir test, güçlü
 *     olduğu dersi hiç sormadan öğrenciyi başa gönderirdi
 *
 * Test ZORUNLU DEĞİL. Atlayan öğrenci baştan başlar ve hiçbir şey kaybetmez.
 */
final readonly class StartPlacement
{
    /** Soru sayısı tavanı — tamamlanma oranıyla ölçüm gücü arasındaki denge. */
    private const QUESTION_CAP = 12;

    /** Ders başına hedef soru; ders sayısı azsa bu sayı yukarı esner. */
    private const PER_COURSE = 2;

    /**
     * Seviye testinde kullanılabilen tipler.
     *
     * Dokunmatik sürükleme (eşleştirme, sıralama, kelime dizme) burada yok:
     * ilk 90 saniyede öğrenciye arayüz öğretmeye çalışmak, bilgi ölçümünü
     * arayüz becerisiyle karıştırır.
     */
    private const TYPES = [
        ExerciseType::MultipleChoice,
        ExerciseType::TrueFalse,
        ExerciseType::FillBlank,
        ExerciseType::NumericInput,
    ];

    public function __construct(
        private ClockInterface $clock,
        private LearnerProfileReader $profiles,
        private VariantCourseReader $variantCourses,
    ) {}

    public function __invoke(int $userId): PlacementAttempt
    {
        $profile = $this->profiles->for($userId);
        $variantId = $profile->primaryExamVariantId
            ?? throw LearningException::noEnrollment();

        /*
         | Yarıda kalmış deneme varsa AYNI sorularla devam ediliyor.
         | Uygulama kapanıp açıldığında yeni soru üretmek, öğrencinin zor
         | soruyu "yeniden başlat" ile eleyebilmesi demekti.
         */
        $open = PlacementAttempt::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if ($open !== null) {
            return $open;
        }

        $questions = $this->pickQuestions($variantId);

        if ($questions === []) {
            throw LearningException::poolInsufficient(self::QUESTION_CAP, 0);
        }

        return DB::transaction(fn (): PlacementAttempt => PlacementAttempt::query()->create([
            'user_id' => $userId,
            'exam_variant_id' => $variantId,
            'status' => 'active',
            'questions' => $questions,
            'question_count' => count($questions),
            'started_at' => $this->clock->now(),
        ]));
    }

    /**
     * Derslere dağıtılmış soru seti.
     *
     * @return list<array<string, mixed>>
     */
    private function pickQuestions(int $variantId): array
    {
        $courses = $this->variantCourses->coursesFor($variantId);

        // Yayınlanmamış ve "yakında" dersler ölçüme girmez: öğrencinin
        // çözemeyeceği bir dersten soru sormak, onu olmayan bir içerikte
        // başarısız saymak olurdu.
        $courses = array_values(array_filter(
            $courses,
            static fn ($row): bool => $row->published,
        ));

        if ($courses === []) {
            return [];
        }

        // Ağırlıklı ders önce: sınavda 40 soru getiren Matematik, 6 soru
        // getiren Biyoloji'den önce yer almalı.
        usort($courses, static fn ($a, $b): int => ($b->examWeight ?? 0) <=> ($a->examWeight ?? 0));

        $perCourse = max(
            self::PER_COURSE,
            (int) ceil(self::QUESTION_CAP / count($courses)),
        );

        $picked = [];

        foreach ($courses as $row) {
            if (count($picked) >= self::QUESTION_CAP) {
                break;
            }

            $room = min($perCourse, self::QUESTION_CAP - count($picked));

            foreach ($this->questionsForCourse($row->courseId, $room) as $question) {
                $picked[] = $question + ['course_name' => $row->shortName ?? $row->name];
            }
        }

        return $picked;
    }

    /** @return list<array<string, mixed>> */
    private function questionsForCourse(int $courseId, int $limit): array
    {
        $topicIds = DB::table('unit_topics')
            ->join('units', 'units.id', '=', 'unit_topics.unit_id')
            ->where('units.course_id', $courseId)
            ->where('units.status', 'published')
            ->distinct()
            ->pluck('unit_topics.topic_id')
            ->all();

        if ($topicIds === []) {
            return [];
        }

        /*
         | Kolaydan zora sıralanıyor ve zorluk başına en fazla bir soru
         | alınıyor. Üç sorunun üçü de 5. seviyeden gelirse test, "bu
         | öğrenci hiçbir şey bilmiyor" der — oysa ölçtüğü şey yalnızca
         | soruların zorluğudur.
         */
        $pool = Exercise::query()
            ->whereIn('topic_id', $topicIds)
            ->where('status', 'published')
            ->whereIn('type', array_map(static fn (ExerciseType $t): string => $t->value, self::TYPES))
            ->orderBy('difficulty')
            ->get(['id', 'type', 'difficulty', 'content', 'answer_key']);

        if ($pool->isEmpty()) {
            return [];
        }

        $byDifficulty = $pool->groupBy('difficulty');
        $questions = [];

        foreach ($byDifficulty as $group) {
            if (count($questions) >= $limit) {
                break;
            }

            /** @var Exercise $exercise */
            $exercise = $group->random();

            $questions[] = [
                'exercise_id' => $exercise->id,
                'course_id' => $courseId,
                'type' => $exercise->getRawOriginal('type'),
                'difficulty' => $exercise->difficulty,
                // Snapshot: içerik ekibi test sürerken soruyu düzeltirse
                // öğrencinin gördüğü soru ile puanlanan soru ayrışmamalı.
                'content' => $exercise->content,
                'answer_key' => $exercise->answer_key,
            ];
        }

        return $questions;
    }
}
