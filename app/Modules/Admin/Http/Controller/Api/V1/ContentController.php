<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controller\Api\V1;

use App\Modules\Admin\Application\UseCase\RecordAudit;
use App\Modules\Admin\Http\Controller\AdminController;
use App\Modules\Catalog\Application\UseCase\CreateUnitFromTemplate;
use App\Modules\Catalog\Application\UseCase\CreateUnitFromTemplateCommand;
use App\Modules\Catalog\Application\UseCase\ImportContentPackage;
use App\Modules\Catalog\Application\UseCase\ValidateSelectionRule;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Topic;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Unit;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitTemplate;
use App\Shared\Domain\Enum\AccessLevel;
use App\Shared\Domain\Enum\PublishStatus;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * İçerik ekibinin panel uçları.
 *
 * Tasarım kararı: bu panel her tabloya CRUD açmaz. İçerik ekibinin gerçekte
 * yaptığı işler — paket içe aktar, şablondan ünite kur, soru düzenle, kuralı
 * önizle, yayınla — birinci sınıf uç olarak duruyor. Ham CRUD, kural
 * motorunun etrafından dolaşmayı kolaylaştırır.
 */
final class ContentController extends AdminController
{
    /** Ünite şablonları — "şablondan ünite oluştur" formunun seçenekleri. */
    public function templates(): JsonResponse
    {
        $templates = UnitTemplate::query()->orderByDesc('is_default')->get()
            ->map(fn (UnitTemplate $t): array => [
                'code' => $t->code,
                'name' => $t->name,
                'description' => $t->description,
                'is_default' => $t->is_default,
                // Editör hangi node'ların üretileceğini önceden görmeli.
                'nodes' => array_map(static fn (array $n): array => [
                    'title' => $n['title'],
                    'type' => $n['node_type'],
                    'difficulty' => $n['difficulty'],
                    'exercise_count' => $n['exercise_count'],
                ], $t->nodes),
            ]);

        return ApiResponse::data($templates->all());
    }

    /** Ders ağacı — panelin sol menüsü. */
    public function courses(): JsonResponse
    {
        $courses = Course::query()
            ->withCount(['units'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Course $c): array => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'scope' => $c->scope->value,
                'status' => $c->status->value,
                'unit_count' => $c->units_count,
            ]);

        return ApiResponse::data($courses->all());
    }

    public function units(Course $course): JsonResponse
    {
        $units = $course->units()
            ->withCount('nodes')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Unit $u): array => [
                'id' => $u->id,
                'title' => $u->title,
                'sort_order' => $u->sort_order,
                'grade_level' => $u->grade_level,
                'status' => $u->status->value,
                'access' => $u->access->value,
                'node_count' => $u->nodes_count,
                'exercise_count' => Exercise::query()->where('owner_unit_id', $u->id)->count(),
            ]);

        return ApiResponse::data($units->all());
    }

    /**
     * Dersin konuları — soru ve ünite formlarının konu seçici verisi.
     *
     * Konular ders değil SUBJECT seviyesinde tutuluyor; bu yüzden liste
     * dersin subject'inden gelir ve TYT/AYT aynı konuları paylaşır.
     */
    public function topics(Course $course): JsonResponse
    {
        $topics = Topic::query()
            ->where('subject_id', $course->subject_id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Topic $t): array => array_filter([
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'parent_id' => $t->parent_id,
                'grade_level' => $t->grade_level,
                'exercise_count' => Exercise::query()->where('topic_id', $t->id)->count(),
            ], static fn ($v): bool => $v !== null));

        return ApiResponse::data([
            'course' => ['id' => $course->id, 'name' => $course->name],
            'subject_id' => $course->subject_id,
            'topics' => $topics->all(),
        ]);
    }

    /** Şablondan ünite üretir — node'lar kural, XP ve kilitleriyle hazır gelir. */
    public function storeUnit(Request $request, CreateUnitFromTemplate $create, RecordAudit $audit): JsonResponse
    {
        $data = $request->validate([
            'course_code' => ['required', 'string', 'exists:courses,code'],
            'template_code' => ['required', 'string', 'exists:unit_templates,code'],
            'title' => ['required', 'string', 'max:191'],
            'topic_ids' => ['required', 'array', 'min:1'],
            'topic_ids.*' => ['integer', 'exists:topics,id'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
            'grade_level' => ['nullable', 'integer', 'min:8', 'max:12'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $unit = $create(new CreateUnitFromTemplateCommand(
                courseCode: $data['course_code'],
                templateCode: $data['template_code'],
                title: $data['title'],
                topicIds: array_values(array_map('intval', $data['topic_ids'])),
                sortOrder: $data['sort_order'] ?? 1,
                gradeLevel: $data['grade_level'] ?? null,
                access: AccessLevel::Free,
                estimatedMinutes: $data['estimated_minutes'] ?? null,
                status: PublishStatus::Draft,
            ));
        } catch (InvalidArgumentException $e) {
            // Dersin subject'ine ait olmayan konu seçilmiş. Kullanıcı hatası,
            // sunucu hatası değil — 500 yerine anlaşılır bir 422 dönmeli.
            return ApiResponse::error('TOPIC_MISMATCH', $e->getMessage(), 422);
        }

        $audit($this->adminId($request), 'unit.created', $unit, null, $request->ip());

        return ApiResponse::data([
            'id' => $unit->id,
            'title' => $unit->title,
            'status' => $unit->status->value,
            'nodes' => $unit->nodes->map(fn (UnitNode $n): array => [
                'id' => $n->id,
                'title' => $n->title,
                'type' => $n->node_type->value,
                'exercise_count' => $n->exercise_count,
                'xp_reward' => $n->xp_reward,
            ])->all(),
        ], 201);
    }

    /**
     * Kuralı kuru çalıştırır: "bu node kaç soru getiriyor?"
     *
     * Havuz modelinin tek gerçek riski kuralın yetersiz soru getirmesidir.
     * Bu uç, editörün yayınlamadan önce görmesini sağlar.
     */
    public function previewSelection(UnitNode $node, ValidateSelectionRule $validate): JsonResponse
    {
        $report = $validate($node);

        return ApiResponse::data([
            'node_id' => $report->nodeId,
            'node_title' => $report->nodeTitle,
            'required' => $report->required,
            'available' => $report->available,
            'relaxed' => $report->relaxed,
            'passes' => $report->passes(),
            'message' => $report->message(),
        ]);
    }

    /** Soru içe aktarma — seeder ile aynı yolu kullanır. */
    public function importPackage(Request $request, ImportContentPackage $import, RecordAudit $audit): JsonResponse
    {
        $package = $request->validate([
            'course' => ['required', 'string', 'exists:courses,code'],
            'subject' => ['required', 'string', 'exists:subjects,code'],
            'unit' => ['required', 'array'],
            'unit.title' => ['required', 'string'],
            'unit.template' => ['required', 'string', 'exists:unit_templates,code'],
            'topics' => ['required', 'array', 'min:1'],
            'exercises' => ['required', 'array', 'min:1'],
        ]);

        $report = $import($request->all());

        $audit(
            $this->adminId($request),
            'content.imported',
            Unit::query()->findOrFail($report->unitId),
            null,
            $request->ip(),
        );

        return ApiResponse::data([
            'unit_id' => $report->unitId,
            'unit_title' => $report->unitTitle,
            'topics' => $report->topicCount,
            'nodes' => $report->nodeCount,
            'exercises' => $report->exerciseCount,
        ], 201);
    }

    /**
     * Yayınlama — YAYIN KAPISI burada.
     *
     * Ünitenin node'larının hepsi yeterli soru getirmiyorsa yayın engellenir.
     * "Şimdilik yayınlayalım, sonra soru ekleriz" yolu bilinçli olarak kapalı:
     * o yol, öğrenciye boş bir tur göstermekle biter.
     */
    public function publishUnit(Request $request, Unit $unit, ValidateSelectionRule $validate, RecordAudit $audit): JsonResponse
    {
        $before = $unit->getAttributes();

        $blocking = $unit->nodes()
            ->get()
            ->map(fn (UnitNode $node) => $validate($node))
            ->reject->passes();

        if ($blocking->isNotEmpty()) {
            return ApiResponse::error(
                'CONTENT_NOT_PUBLISHABLE',
                'Bazı adımlar yeterli soru getirmiyor.',
                422,
                ['blocking' => $blocking->map(fn ($r): array => [
                    'node_id' => $r->nodeId,
                    'node_title' => $r->nodeTitle,
                    'message' => $r->message(),
                ])->values()->all()],
            );
        }

        $unit->update(['status' => PublishStatus::Published, 'published_at' => now()]);
        $unit->nodes()->update(['status' => PublishStatus::Published]);
        Exercise::query()->where('owner_unit_id', $unit->id)->update(['status' => PublishStatus::Published]);

        $audit($this->adminId($request), 'unit.published', $unit, $before, $request->ip());

        return ApiResponse::data([
            'id' => $unit->id,
            'status' => $unit->status->value,
            'published_nodes' => $unit->nodes()->count(),
        ]);
    }
}
