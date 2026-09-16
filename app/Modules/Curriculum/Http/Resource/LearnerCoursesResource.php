<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Http\Resource;

use App\Modules\Curriculum\Application\UseCase\LearnerCoursesView;
use App\Modules\Curriculum\Application\UseCase\LearnerCourseView;
use App\Modules\Curriculum\Application\UseCase\SectionCoursesView;

/** Tasarımdaki "Öğren · ders seçimi" ekranının yanıt şekli. */
final readonly class LearnerCoursesResource
{
    /** @return array<string, mixed> */
    public static function toArray(LearnerCoursesView $view): array
    {
        return [
            'exam_variant' => [
                'code' => $view->variant->code,
                'name' => $view->variant->name,
                'field' => $view->variant->field_code->value,
                'description' => $view->variant->description,
            ],
            'sections' => array_map(self::section(...), $view->sections),
        ];
    }

    /** @return array<string, mixed> */
    private static function section(SectionCoursesView $section): array
    {
        return [
            'code' => $section->section->code,
            'name' => $section->section->name,
            'duration_min' => $section->section->duration_min,
            'question_count' => $section->section->question_count,
            'courses' => array_map(self::course(...), $section->courses),
        ];
    }

    /** @return array<string, mixed> */
    private static function course(LearnerCourseView $view): array
    {
        $row = $view->row;

        return array_filter([
            'id' => $row->courseId,
            'code' => $row->code,
            'name' => $row->shortName ?? $row->name,
            'full_name' => $row->name,
            'color' => $row->color,
            'icon' => $row->icon,
            'scope' => $row->scope,
            'access' => $row->access,
            'exam_weight' => $row->examWeight,
            'coming_soon' => $view->comingSoon ?: null,
            'placeholder_label' => $view->placeholderLabel,
            'locked' => $view->locked ?: null,
            'lock_reason' => $view->lockReason(),
        ], static fn ($v): bool => $v !== null);
    }
}
