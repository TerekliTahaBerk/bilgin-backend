<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCase;

use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Subject;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Topic;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Unit;
use App\Shared\Domain\Enum\AccessLevel;
use App\Shared\Domain\Enum\PublishStatus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bir içerik paketini (konular + ünite + soru havuzu) içe aktarır.
 *
 * Seeder'ın ve admin panelindeki toplu içe aktarmanın ortak yolu budur:
 * içerik kod içinde değil, veri dosyasında yaşar. Binlerce sorunun elle
 * PHP'ye yazılması operasyonel olarak sürdürülemez.
 */
final readonly class ImportContentPackage
{
    public function __construct(private CreateUnitFromTemplate $createUnit) {}

    /** @param  array<string, mixed>  $package */
    public function __invoke(array $package): ImportReport
    {
        return DB::transaction(function () use ($package): ImportReport {
            $subject = Subject::query()->where('code', $package['subject'])->first()
                ?? throw new RuntimeException("Subject bulunamadı: {$package['subject']}");

            $course = Course::query()->where('code', $package['course'])->first()
                ?? throw new RuntimeException("Course bulunamadı: {$package['course']}");

            $topicIds = $this->upsertTopics($subject->id, $package['topics']);
            $unit = $this->upsertUnit($package, array_values($topicIds));
            $imported = $this->upsertExercises($package['exercises'], $topicIds, $course->id, $unit->id);

            return new ImportReport(
                unitId: $unit->id,
                unitTitle: $unit->title,
                topicCount: count($topicIds),
                exerciseCount: $imported,
                nodeCount: $unit->nodes()->count(),
            );
        });
    }

    /**
     * @param  list<array<string, mixed>>  $topics
     * @return array<string, int> topic kodu → id
     */
    private function upsertTopics(int $subjectId, array $topics): array
    {
        $ids = [];

        foreach ($topics as $index => $topic) {
            $model = Topic::query()->updateOrCreate(
                ['subject_id' => $subjectId, 'code' => $topic['code']],
                [
                    'name' => $topic['name'],
                    'grade_level' => $topic['grade_level'] ?? null,
                    'exam_weight' => $topic['exam_weight'] ?? null,
                    'sort_order' => $index + 1,
                ],
            );

            $ids[$topic['code']] = $model->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $package
     * @param  list<int>  $topicIds
     */
    private function upsertUnit(array $package, array $topicIds): Unit
    {
        $spec = $package['unit'];

        $existing = Unit::query()
            ->whereHas('course', fn ($q) => $q->where('code', $package['course']))
            ->where('title', $spec['title'])
            ->first();

        // Yeniden çalıştırılabilirlik: ünite varsa node'ları şablondan
        // yeniden üretmek yerine olduğu gibi bırakılır — içerik ekibinin
        // elle yaptığı düzenlemeler seeder tarafından ezilmemelidir.
        if ($existing !== null) {
            $existing->topics()->syncWithoutDetaching(array_fill_keys($topicIds, ['weight' => 1]));

            return $existing;
        }

        return ($this->createUnit)(new CreateUnitFromTemplateCommand(
            courseCode: $package['course'],
            templateCode: $spec['template'],
            title: $spec['title'],
            topicIds: $topicIds,
            sortOrder: $spec['sort_order'] ?? 1,
            description: $spec['description'] ?? null,
            gradeLevel: $spec['grade_level'] ?? null,
            difficultyBand: $spec['difficulty_band'] ?? null,
            access: AccessLevel::from($spec['access'] ?? 'free'),
            estimatedMinutes: $spec['estimated_minutes'] ?? null,
            status: PublishStatus::from($spec['status'] ?? 'draft'),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $exercises
     * @param  array<string, int>  $topicIds
     */
    private function upsertExercises(array $exercises, array $topicIds, int $courseId, int $unitId): int
    {
        $count = 0;

        foreach ($exercises as $exercise) {
            $topicId = $topicIds[$exercise['topic']]
                ?? throw new RuntimeException("Pakette tanımsız konu: {$exercise['topic']}");

            // İdempotent anahtar: aynı konu + tip + soru gövdesi ikinci kez eklenmez.
            $fingerprint = hash('sha256', $topicId.$exercise['type'].json_encode($exercise['content']));

            Exercise::query()->updateOrCreate(
                ['uuid' => $this->deterministicUuid($fingerprint)],
                [
                    'topic_id' => $topicId,
                    'owner_course_id' => $courseId,
                    'owner_unit_id' => $unitId,
                    'type' => $exercise['type'],
                    'content' => $exercise['content'],
                    'answer_key' => $exercise['answer_key'],
                    'explanation' => $exercise['explanation'] ?? null,
                    'difficulty' => $exercise['difficulty'] ?? 3,
                    'applicable_scopes' => $exercise['scopes'] ?? ['tyt'],
                    'status' => PublishStatus::from($exercise['status'] ?? 'published'),
                ],
            );

            $count++;
        }

        return $count;
    }

    /** Parmak izinden türetilen kararlı UUID — seeder tekrar çalışsa da çoğalmaz. */
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
