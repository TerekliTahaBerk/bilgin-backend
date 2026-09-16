<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Selection;

use App\Modules\Catalog\Domain\Enum\SelectionMode;
use InvalidArgumentException;

/**
 * unit_nodes.selection_rule jsonb'sinin tiplenmiş hâli.
 *
 * Bilinçli olarak bir DSL DEĞİL: sabit şemalı, doğrulanabilir bir yapı.
 * Panelde form ile üretilir, elle yazılmaz.
 */
final readonly class SelectionRule
{
    /**
     * @param  list<int>  $topicIds  boşsa üniteden miras alınır
     * @param  list<string>  $types
     * @param  list<int>  $exerciseIds  yalnızca Fixed modunda
     */
    private function __construct(
        public SelectionMode $mode,
        public int $count,
        public array $topicIds,
        public bool $inheritTopicsFromUnit,
        public ?string $scope,
        public int $difficultyMin,
        public int $difficultyMax,
        public array $types,
        public int $excludeSeenDays,
        public string $fallback,
        public array $exerciseIds,
        public ?int $blueprintId,
        public float $weakTopicRatio,
    ) {}

    /** @param  array<string, mixed>  $raw */
    public static function fromArray(array $raw): self
    {
        $mode = SelectionMode::tryFrom($raw['mode'] ?? '')
            ?? throw new InvalidArgumentException('selection_rule.mode geçersiz: '.($raw['mode'] ?? 'null'));

        $filters = $raw['filters'] ?? [];
        $topics = $filters['topics'] ?? [];
        $difficulty = $filters['difficulty'] ?? [];

        return new self(
            mode: $mode,
            count: (int) ($raw['count'] ?? 0),
            topicIds: is_array($topics) ? array_values(array_map('intval', $topics)) : [],
            inheritTopicsFromUnit: $topics === 'inherit_from_unit',
            scope: $filters['scope'] ?? null,
            difficultyMin: (int) ($difficulty['min'] ?? 1),
            difficultyMax: (int) ($difficulty['max'] ?? 5),
            types: array_values($filters['types'] ?? []),
            excludeSeenDays: (int) ($filters['exclude_seen_days'] ?? 0),
            fallback: $raw['fallback'] ?? 'none',
            exerciseIds: array_values(array_map('intval', $raw['exercise_ids'] ?? [])),
            blueprintId: isset($raw['blueprint_id']) ? (int) $raw['blueprint_id'] : null,
            weakTopicRatio: (float) ($raw['weak_topic_ratio'] ?? 0.0),
        );
    }

    public function allowsRelaxing(): bool
    {
        return $this->fallback === 'relax_difficulty';
    }
}
