<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCase;

use App\Modules\Catalog\Domain\Enum\DifficultyLevel;
use App\Modules\Catalog\Domain\Enum\NodeType;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Topic;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Unit;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitTemplate;
use App\Shared\Domain\Enum\AccessLevel;
use App\Shared\Domain\Enum\PublishStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Şablondan ünite üretir: node'lar selection_rule, unlock_rule ve XP'siyle hazır gelir.
 *
 * 21 ders × ~8 ünite × ~6 node ≈ 1000 node. Bunu elle kurmak içerik ekibini
 * boğar; şablon bu operasyonel yükü ortadan kaldırır. İçerik ekibi yalnızca
 * soru yazar, istisna durumda node'u tek tek düzenler.
 */
final readonly class CreateUnitFromTemplate
{
    public function __invoke(CreateUnitFromTemplateCommand $command): Unit
    {
        return DB::transaction(function () use ($command): Unit {
            $course = Course::query()->where('code', $command->courseCode)->firstOrFail();
            $template = UnitTemplate::query()->where('code', $command->templateCode)->firstOrFail();

            $this->assertTopicsBelongToCourse($course, $command->topicIds);

            $unit = Unit::query()->create([
                'course_id' => $course->id,
                'title' => $command->title,
                'description' => $command->description,
                'sort_order' => $command->sortOrder,
                'grade_level' => $command->gradeLevel,
                'difficulty_band' => $command->difficultyBand,
                'access' => $command->access,
                'estimated_minutes' => $command->estimatedMinutes,
                'status' => $command->status,
                'published_at' => $command->status === PublishStatus::Published ? now() : null,
            ]);

            $unit->topics()->sync(array_fill_keys($command->topicIds, ['weight' => 1]));

            foreach ($template->nodes as $index => $spec) {
                $this->createNode($unit, $spec, $index, $course->scope->value, $command->status);
            }

            return $unit->load('nodes');
        });
    }

    /**
     * Ünitenin konuları dersin subject'ine ait olmalı.
     *
     * Aksi hâlde bir Coğrafya ünitesine Tarih konusu iliştirilebilir ve
     * havuz kuralı konuya göre çalıştığı için öğrenciye Coğrafya dersinde
     * Tarih sorusu çıkar. Yayın kapısı bunu yakalayamaz — kural teknik
     * olarak "yeterli soru" bulmuştur.
     *
     * @param  list<int>  $topicIds
     */
    private function assertTopicsBelongToCourse(Course $course, array $topicIds): void
    {
        $foreign = Topic::query()
            ->whereIn('id', $topicIds)
            ->where('subject_id', '!=', $course->subject_id)
            ->pluck('name');

        if ($foreign->isNotEmpty()) {
            throw new InvalidArgumentException(
                "Bu konular {$course->name} dersine ait değil: ".$foreign->implode(', ')
            );
        }
    }

    /** @param  array<string, mixed>  $spec */
    private function createNode(
        Unit $unit,
        array $spec,
        int $index,
        string $scope,
        PublishStatus $status,
    ): UnitNode {
        $nodeType = NodeType::from($spec['node_type']);
        $difficulty = DifficultyLevel::from($spec['difficulty']);
        $count = (int) $spec['exercise_count'];

        return UnitNode::query()->create([
            'unit_id' => $unit->id,
            'title' => $spec['title'],
            'node_type' => $nodeType,
            'difficulty' => $difficulty,
            'sort_order' => $index + 1,
            'exercise_count' => $count,
            'time_limit_sec' => $spec['time_limit_sec'] ?? null,
            'consumes_hearts' => $spec['consumes_hearts'] ?? $nodeType->consumesHeartsByDefault(),
            'xp_reward' => (int) round($difficulty->baseXp() * $nodeType->xpMultiplier()),
            'access' => AccessLevel::from($spec['access'] ?? 'free'),
            'selection_rule' => $this->selectionRuleFor($spec, $difficulty, $count, $scope),
            'unlock_rule' => $this->unlockRuleFor($nodeType, $index),
            'preview_label' => $spec['preview_label'] ?? null,
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    private function selectionRuleFor(array $spec, DifficultyLevel $difficulty, int $count, string $scope): array
    {
        if (($spec['mode'] ?? 'pool') === 'review_queue') {
            return ['mode' => 'review_queue', 'count' => $count, 'scope' => 'unit'];
        }

        [$min, $max] = $difficulty->exerciseDifficultyRange();

        return [
            'mode' => 'pool',
            'count' => $count,
            'filters' => [
                'topics' => 'inherit_from_unit',
                'difficulty' => ['min' => $min, 'max' => $max],
                'scope' => $scope,
                'types' => $spec['types'] ?? [],
                'exclude_seen_days' => $spec['exclude_seen_days'] ?? 14,
            ],
            'distribution' => ['by_topic' => 'even'],
            'fallback' => 'relax_difficulty',
        ];
    }

    /**
     * Varsayılan kilit zinciri. İlk node açık; sonrakiler bir öncekine bağlı;
     * ünite challenge'ı tüm çalışma node'larının bitmesini bekler.
     */
    /** @return array<string, mixed>|null */
    private function unlockRuleFor(NodeType $nodeType, int $index): ?array
    {
        if ($index === 0) {
            return null;
        }

        if ($nodeType === NodeType::UnitChallenge) {
            return [
                'type' => 'all_of',
                'rules' => [
                    ['type' => 'unit_study_nodes_completed'],
                    ['type' => 'min_accuracy', 'value' => 80],
                ],
            ];
        }

        return ['type' => 'previous_node_completed'];
    }
}
