<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Infrastructure\Eloquent\Repository;

use App\Modules\Catalog\Domain\Blueprint\BlueprintItemSpec;
use App\Modules\Catalog\Domain\Blueprint\BlueprintSpec;
use App\Modules\Catalog\Domain\Contract\BlueprintReader;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamBlueprint;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamBlueprintItem;
use App\Shared\Domain\Enum\PublishStatus;

final class EloquentBlueprintReader implements BlueprintReader
{
    public function find(int $blueprintId): ?BlueprintSpec
    {
        $blueprint = ExamBlueprint::query()->with('items')->find($blueprintId);

        return $blueprint === null ? null : $this->toSpec($blueprint);
    }

    /** @return list<BlueprintSpec> */
    public function forExamVariant(int $examVariantId): array
    {
        $specs = ExamBlueprint::query()
            ->with('items')
            ->where('status', PublishStatus::Published)
            // Varyanta özel denemeler VE varyanttan bağımsız genel denemeler
            // (ör. TYT herkese ortak) birlikte döner.
            ->where(fn ($q) => $q->where('exam_variant_id', $examVariantId)->orWhereNull('exam_variant_id'))
            ->get()
            ->map($this->toSpec(...))
            ->all();

        return array_values($specs);
    }

    private function toSpec(ExamBlueprint $blueprint): BlueprintSpec
    {
        $rule = $blueprint->scoring_rule ?? [];

        return new BlueprintSpec(
            id: (int) $blueprint->id,
            code: (string) $blueprint->code,
            name: (string) $blueprint->name,
            durationMin: (int) $blueprint->duration_min,
            penaltyRatio: (float) ($rule['penalty_ratio'] ?? 0),
            baseScore: (int) ($rule['base_score'] ?? 100),
            items: array_values($blueprint->items
                ->map(fn (ExamBlueprintItem $item): BlueprintItemSpec => new BlueprintItemSpec(
                    courseId: $item->course_id !== null ? (int) $item->course_id : null,
                    topicId: $item->topic_id !== null ? (int) $item->topic_id : null,
                    questionCount: (int) $item->question_count,
                    difficultyDistribution: array_map(
                        static fn ($v): float => (float) $v,
                        $item->difficulty_distribution ?? [],
                    ),
                ))
                ->all()),
        );
    }
}
