<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controller\Api\V1;

use App\Modules\Admin\Application\UseCase\RecordAudit;
use App\Modules\Admin\Http\Controller\AdminController;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamSection;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamVariant;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Alan müfredatını panelden düzenler.
 *
 * Bu ucun varlık sebebi ders sisteminin temel iddiası: "hangi alan hangi
 * dersi, hangi sırada, hangi sekmede, ücretsiz mi görür" bir VERİ kararıdır.
 * ÖSYM bir dersi alandan çıkardığında yapılması gereken şey deploy değil,
 * bu ekranda birkaç satır düzenlemek.
 */
final class CurriculumMapController extends AdminController
{
    /**
     * Eşleme ekranının seçenek kaynağı: varyantlar ve sınav oturumları.
     *
     * Panelin bu uca ihtiyacı var çünkü varyant ve oturum kimlikleri tohum
     * verisinden gelir ve ortamdan ortama değişir. Panelde sabit kimlik
     * yazmak, yanlış varyantın müfredatını sessizce ezmekle biterdi.
     *
     * Öğrenci ucu kullanılmaz: o uç kayıtlı öğrenciye göre filtreler ve
     * panelin görmesi gereken pasif varyantları gizler.
     */
    public function options(): JsonResponse
    {
        $variants = ExamVariant::query()
            ->orderBy('exam_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ExamVariant $v): array => [
                'id' => $v->id,
                'exam_id' => $v->exam_id,
                'code' => $v->code,
                'name' => $v->name,
                'field_code' => $v->field_code->value,
                'sort_order' => $v->sort_order,
                'is_active' => $v->is_active,
            ]);

        $sections = ExamSection::query()
            ->orderBy('exam_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ExamSection $s): array => [
                'id' => $s->id,
                'exam_id' => $s->exam_id,
                'code' => $s->code,
                'name' => $s->name,
                'sort_order' => $s->sort_order,
            ]);

        return ApiResponse::data([
            'variants' => $variants->all(),
            'sections' => $sections->all(),
        ]);
    }

    public function show(ExamVariant $variant): JsonResponse
    {
        $rows = DB::table('exam_variant_courses as evc')
            ->join('courses as c', 'c.id', '=', 'evc.course_id')
            ->join('exam_sections as s', 's.id', '=', 'evc.exam_section_id')
            ->where('evc.exam_variant_id', $variant->id)
            ->orderBy('evc.sort_order')
            ->get([
                'c.id as course_id', 'c.code', 'c.name', 'c.status',
                's.code as section_code', 'evc.exam_section_id',
                'evc.sort_order', 'evc.access', 'evc.exam_weight', 'evc.is_required',
            ]);

        return ApiResponse::data([
            'exam_variant' => ['code' => $variant->code, 'name' => $variant->name],
            'courses' => $rows->all(),
        ]);
    }

    public function update(Request $request, ExamVariant $variant, RecordAudit $audit): JsonResponse
    {
        $data = $request->validate([
            'courses' => ['required', 'array', 'min:1'],
            'courses.*.course_id' => ['required', 'integer', 'exists:courses,id'],
            'courses.*.exam_section_id' => ['required', 'integer', 'exists:exam_sections,id'],
            'courses.*.sort_order' => ['required', 'integer', 'min:1'],
            'courses.*.access' => ['required', 'string', 'in:free,premium'],
            'courses.*.exam_weight' => ['nullable', 'integer', 'min:0', 'max:200'],
            'courses.*.is_required' => ['nullable', 'boolean'],
        ]);

        $before = DB::table('exam_variant_courses')
            ->where('exam_variant_id', $variant->id)
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();

        DB::transaction(function () use ($variant, $data): void {
            // Tam değiştirme (PUT semantiği): listede olmayan ders kaldırılır.
            // Kısmi güncelleme, "bu dersi çıkardım" niyetini ifade edemezdi.
            DB::table('exam_variant_courses')->where('exam_variant_id', $variant->id)->delete();

            foreach ($data['courses'] as $row) {
                DB::table('exam_variant_courses')->insert([
                    'exam_variant_id' => $variant->id,
                    'course_id' => $row['course_id'],
                    'exam_section_id' => $row['exam_section_id'],
                    'sort_order' => $row['sort_order'],
                    'access' => $row['access'],
                    'exam_weight' => $row['exam_weight'] ?? null,
                    'is_required' => $row['is_required'] ?? true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $audit($this->adminId($request), 'curriculum.updated', $variant, ['courses' => $before], $request->ip());

        return ApiResponse::data([
            'exam_variant' => $variant->code,
            'course_count' => count($data['courses']),
        ]);
    }
}
