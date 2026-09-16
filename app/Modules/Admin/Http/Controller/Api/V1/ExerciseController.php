<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controller\Api\V1;

use App\Modules\Admin\Application\UseCase\RecordAudit;
use App\Modules\Admin\Http\Controller\AdminController;
use App\Modules\Catalog\Domain\Enum\ExerciseType;
use App\Modules\Catalog\Domain\Validation\ValidatorRegistry;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Topic;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Unit;
use App\Shared\Domain\Enum\PublishStatus;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Soru düzenleme — içerik ekibinin günlük işi.
 *
 * Panelde cevap anahtarı GÖRÜNÜR (editör onu düzenliyor); öğrenci API'sinde
 * asla görünmez. İki ayrı guard'ın ayrı olmasının somut faydası bu.
 */
final class ExerciseController extends AdminController
{
    public function index(Request $request, Unit $unit): JsonResponse
    {
        $exercises = Exercise::query()
            ->where('owner_unit_id', $unit->id)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->with('topic:id,name')
            ->orderBy('id')
            ->get();

        return ApiResponse::data([
            'unit' => ['id' => $unit->id, 'title' => $unit->title],
            'exercises' => $exercises->map(fn (Exercise $e): array => [
                'id' => $e->id,
                'type' => $e->type->value,
                'topic' => ['id' => $e->topic_id, 'name' => $e->topic->name],
                'difficulty' => $e->difficulty,
                'status' => $e->status->value,
                'version' => $e->version,
                'scopes' => $e->applicable_scopes,
                'preview' => $this->preview($e),
                'stats' => $this->stats($e),
            ])->all(),
        ]);
    }

    /** Tek soru — cevap anahtarı DAHİL (editör onu düzenliyor). */
    public function show(Exercise $exercise): JsonResponse
    {
        return ApiResponse::data([
            'id' => $exercise->id,
            'type' => $exercise->type->value,
            'topic_id' => $exercise->topic_id,
            'difficulty' => $exercise->difficulty,
            'content' => $exercise->content,
            'answer_key' => $exercise->answer_key,
            'explanation' => $exercise->explanation,
            'applicable_scopes' => $exercise->applicable_scopes,
            'status' => $exercise->status->value,
            'version' => $exercise->version,
            'stats' => $this->stats($exercise),
        ]);
    }

    public function store(Request $request, ValidatorRegistry $validators, RecordAudit $audit): JsonResponse
    {
        $data = $this->validatePayload($request);

        $type = ExerciseType::from($data['type']);
        $result = $validators->for($type)->validate($data['content'], $data['answer_key']);

        if (! $result->passes()) {
            return $this->schemaError($result->errors);
        }

        /** @var Topic $topic */
        $topic = Topic::query()->findOrFail($data['topic_id']);

        $unit = isset($data['owner_unit_id'])
            ? Unit::query()->with('course')->find($data['owner_unit_id'])
            : null;

        // Ünitenin konusu dersin subject'ine ait olmalı kuralının soru
        // seviyesindeki karşılığı: yanlış konuya yazılan soru, o konunun
        // ustalık istatistiğini bozar.
        if ($unit instanceof Unit && $unit->course->subject_id !== $topic->subject_id) {
            return ApiResponse::error(
                'TOPIC_MISMATCH',
                "'{$topic->name}' konusu {$unit->course->name} dersine ait değil.",
                422,
            );
        }

        $exercise = Exercise::query()->create([
            ...$data,
            'owner_course_id' => $unit instanceof Unit ? $unit->course_id : null,
            // Yeni soru daima taslak: yayın kararı denetçinin.
            'status' => PublishStatus::Draft,
            'version' => 1,
        ]);

        $audit($this->adminId($request), 'exercise.created', $exercise, null, $request->ip());

        return ApiResponse::data(['id' => $exercise->id, 'status' => $exercise->status->value], 201);
    }

    public function update(Request $request, Exercise $exercise, ValidatorRegistry $validators, RecordAudit $audit): JsonResponse
    {
        $data = $this->validatePayload($request, partial: true);

        $content = $data['content'] ?? $exercise->content;
        $answerKey = $data['answer_key'] ?? $exercise->answer_key;
        $type = isset($data['type']) ? ExerciseType::from($data['type']) : $exercise->type;

        $result = $validators->for($type)->validate($content, $answerKey);

        if (! $result->passes()) {
            return $this->schemaError($result->errors);
        }

        $before = $exercise->getAttributes();
        $answerChanged = $answerKey != $exercise->answer_key;

        $exercise->fill([...$data, 'content' => $content, 'answer_key' => $answerKey, 'type' => $type]);

        /*
         | Sürüm her düzenlemede artar.
         |
         | Devam eden oturumlar snapshot'tan okuduğu için etkilenmez;
         | sürüm, geçmiş answer_attempts kayıtlarının HANGİ soruya ait
         | olduğunu ayırt etmeyi sağlar. Cevap anahtarı değiştiyse eski
         | istatistikler artık aynı soruyu ölçmüyordur.
         */
        $exercise->version = $exercise->version + 1;
        $exercise->save();

        $audit($this->adminId($request), 'exercise.updated', $exercise, $before, $request->ip());

        return ApiResponse::data(array_filter([
            'id' => $exercise->id,
            'version' => $exercise->version,
            'answer_key_changed' => $answerChanged ?: null,
            'warning' => $answerChanged && $exercise->status === PublishStatus::Published
                ? 'Cevap anahtarı değişti; bu soru daha önce çözülmüştü. Geçmiş istatistikler artık farklı bir soruyu ölçüyor.'
                : null,
        ], static fn ($v): bool => $v !== null));
    }

    /**
     * Soruyu arşivler — SİLMEZ.
     *
     * Yayınlanmış soru geçmiş oturumlarda ve answer_attempts kayıtlarında
     * referanslı. Silmek, öğrencinin geçmişini ve konu istatistiklerini
     * bozar; arşivlemek soruyu yeni oturumlardan çıkarır, geçmişi korur.
     */
    public function destroy(Request $request, Exercise $exercise, RecordAudit $audit): JsonResponse
    {
        $before = $exercise->getAttributes();

        $exercise->update(['status' => PublishStatus::Archived]);

        $audit($this->adminId($request), 'exercise.archived', $exercise, $before, $request->ip());

        return ApiResponse::data(['id' => $exercise->id, 'status' => $exercise->status->value]);
    }

    /** @return array<string, mixed> */
    private function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'type' => [$required, Rule::enum(ExerciseType::class)],
            'topic_id' => [$required, 'integer', 'exists:topics,id'],
            'owner_unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'content' => [$required, 'array'],
            'answer_key' => [$required, 'array'],
            'explanation' => ['nullable', 'string', 'max:2000'],
            'difficulty' => ['nullable', 'integer', 'min:1', 'max:5'],
            'applicable_scopes' => ['nullable', 'array', 'min:1'],
            'applicable_scopes.*' => ['string', 'in:tyt,ayt,ydt,lgs,kpss,ales,yds'],
        ]);
    }

    /** @param  list<string>  $errors */
    private function schemaError(array $errors): JsonResponse
    {
        return ApiResponse::error(
            'INVALID_EXERCISE_CONTENT',
            'Soru şeması geçersiz.',
            422,
            ['schema_errors' => $errors],
        );
    }

    /** Listede soruyu tanımaya yetecek kısa metin. */
    private function preview(Exercise $exercise): string
    {
        $content = $exercise->content;

        $text = $content['stem']
            ?? $content['statement']
            ?? $content['template']
            ?? $content['instruction']
            ?? $content['front']
            ?? '(önizleme yok)';

        return mb_substr((string) $text, 0, 90);
    }

    /**
     * Soru kalite metriği.
     *
     * %95+ veya %10- doğru oranı, sorunun ya çok kolay ya hatalı olduğunun
     * işareti; editör bunu görmeden hangi soruyu düzelteceğini bilemez.
     *
     * @return array<string, mixed>
     */
    private function stats(Exercise $exercise): array
    {
        $row = DB::table('answer_attempts')
            ->where('exercise_id', $exercise->id)
            ->selectRaw('count(*) as attempts, sum(case when is_correct then 1 else 0 end) as correct, avg(elapsed_ms) as avg_ms')
            ->first();

        $attempts = (int) ($row->attempts ?? 0);
        $correct = (int) ($row->correct ?? 0);
        $avgMs = $row->avg_ms ?? null;
        $rate = $attempts === 0 ? null : (int) round($correct / $attempts * 100);

        return [
            'attempts' => $attempts,
            'correct_rate' => $rate,
            'avg_seconds' => $avgMs === null ? null : (int) round(((float) $avgMs) / 1000),
            // 20 denemeden sonra %95+ veya %10- doğru oranı, sorunun ya çok
            // kolay ya hatalı olduğunun işareti.
            'needs_review' => $attempts >= 20 && $rate !== null && ($rate >= 95 || $rate <= 10),
        ];
    }
}
