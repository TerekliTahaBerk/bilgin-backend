<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resource;

use App\Modules\Catalog\Application\UseCase\CoursePathView;
use App\Modules\Catalog\Application\UseCase\NodePathView;
use App\Modules\Catalog\Application\UseCase\UnitPathView;

/** Tasarımın imza ekranı: ünite yolu. */
final readonly class CoursePathResource
{
    /** @return array<string, mixed> */
    public static function toArray(CoursePathView $view): array
    {
        return [
            'course' => [
                'id' => $view->course->id,
                'code' => $view->course->code,
                'name' => $view->course->name,
                'short_name' => $view->course->short_name,
                'color' => $view->course->color ?? $view->course->subject->color,
                'scope' => $view->course->scope->value,
                'completed_units' => $view->completedUnits(),
                'total_units' => count($view->units),
                'path_strategy' => $view->pathStrategy,
            ],
            'units' => array_map(self::unit(...), $view->units),
        ];
    }

    /** @return array<string, mixed> */
    private static function unit(UnitPathView $view): array
    {
        return array_filter([
            'id' => $view->unit->id,
            'title' => $view->unit->title,
            'grade_level' => $view->unit->grade_level,
            'estimated_minutes' => $view->unit->estimated_minutes,
            'state' => $view->state(),
            'lock_reason' => $view->firstLockReason(),
            'completion_percent' => $view->completionPercent(),
            'completed_nodes' => $view->completedNodes,
            'total_nodes' => $view->totalNodes,
            'nodes' => array_map(self::node(...), $view->nodes),
        ], static fn ($v): bool => $v !== null);
    }

    /** @return array<string, mixed> */
    private static function node(NodePathView $view): array
    {
        $node = $view->node;

        return array_filter([
            'id' => $node->id,
            'title' => $node->title,
            'type' => $node->node_type->value,
            'difficulty' => $node->difficulty->value,
            'state' => $view->state(),
            'lock_reason' => $view->lockReason?->value,
            'exercise_count' => $node->exercise_count,
            'time_limit_sec' => $node->time_limit_sec,
            'consumes_hearts' => $node->consumes_hearts,
            'xp_reward' => $node->xp_reward,
            'access' => $node->access->value,
            'preview_label' => $node->preview_label,
            'best_accuracy' => $view->bestAccuracy ?: null,
        ], static fn ($v): bool => $v !== null);
    }
}
