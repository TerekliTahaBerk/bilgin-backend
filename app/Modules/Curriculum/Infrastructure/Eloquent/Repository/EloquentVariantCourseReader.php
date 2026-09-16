<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Infrastructure\Eloquent\Repository;

use App\Modules\Curriculum\Domain\Contract\VariantCourseReader;
use App\Modules\Curriculum\Domain\ReadModel\VariantCourseRow;
use Illuminate\Support\Facades\DB;

final class EloquentVariantCourseReader implements VariantCourseReader
{
    public function coursesFor(int $examVariantId): array
    {
        $rows = DB::table('exam_variant_courses as evc')
            ->join('courses as c', 'c.id', '=', 'evc.course_id')
            ->leftJoin('subjects as s', 's.id', '=', 'c.subject_id')
            ->where('evc.exam_variant_id', $examVariantId)
            ->orderBy('evc.sort_order')
            ->get([
                'c.id as course_id', 'c.code', 'c.name', 'c.short_name', 'c.scope', 'c.status',
                DB::raw('coalesce(c.color, s.color) as color'),
                DB::raw('coalesce(c.icon, s.icon) as icon'),
                'evc.exam_section_id', 'evc.sort_order', 'evc.access',
                'evc.exam_weight', 'evc.placeholder_label',
            ]);

        $mapped = $rows->map(static fn (object $r): VariantCourseRow => new VariantCourseRow(
            courseId: (int) $r->course_id,
            code: (string) $r->code,
            name: (string) $r->name,
            shortName: $r->short_name !== null ? (string) $r->short_name : null,
            color: $r->color !== null ? (string) $r->color : null,
            icon: $r->icon !== null ? (string) $r->icon : null,
            scope: (string) $r->scope,
            published: $r->status === 'published',
            examSectionId: (int) $r->exam_section_id,
            sortOrder: (int) $r->sort_order,
            access: (string) $r->access,
            examWeight: $r->exam_weight !== null ? (int) $r->exam_weight : null,
            placeholderLabel: $r->placeholder_label !== null ? (string) $r->placeholder_label : null,
        ))->all();

        return array_values($mapped);
    }
}
