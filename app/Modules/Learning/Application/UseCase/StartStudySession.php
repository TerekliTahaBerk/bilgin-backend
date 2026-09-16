<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\UseCase;

use App\Modules\Catalog\Domain\Contract\UnitTopicReader;
use App\Modules\Catalog\Domain\Selection\ExerciseRef;
use App\Modules\Catalog\Domain\Selection\SelectionContext;
use App\Modules\Catalog\Domain\Selection\SelectionRule;
use App\Modules\Catalog\Domain\Selection\SelectorRegistry;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Modules\Learning\Application\DTO\StartSessionCommand;
use App\Modules\Learning\Domain\Exception\LearningException;
use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use App\Modules\Learning\Infrastructure\Eloquent\Model\UserNodeProgress;
use App\Shared\Clock\ClockInterface;
use App\Shared\Domain\Hearts\HeartBalanceReader;
use DateInterval;
use Illuminate\Support\Facades\DB;

/**
 * Bir "tur" başlatır.
 *
 * Sıra önemli ve savunmacı:
 *   1. idempotency — ağ hatasında tekrar gönderilen istek ikinci tur açmaz
 *   2. can kontrolü — can yoksa soru bile seçmeyiz
 *   3. soru seçimi — node'un selection_rule'u çalıştırılır
 *   4. SNAPSHOT — seçilen sorular dondurulur
 *
 * 4. adım olmadan içerik ekibinin araya yaptığı bir düzeltme, devam eden
 * oturumu bozardı. Snapshot bu modelin pazarlığa açık olmayan parçası.
 */
final readonly class StartStudySession
{
    public function __construct(
        private ClockInterface $clock,
        private SelectorRegistry $selectors,
        private UnitTopicReader $unitTopics,
        private HeartBalanceReader $hearts,
    ) {}

    public function __invoke(StartSessionCommand $command): StudySession
    {
        if ($command->idempotencyKey !== null) {
            $existing = StudySession::query()
                ->where('idempotency_key', $command->idempotencyKey)
                ->with('items')
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $node = UnitNode::query()->with('unit.course')->findOrFail($command->nodeId);
        $unit = $node->unit;

        $consumesHearts = $node->consumes_hearts;

        // Can yoksa oturum hiç açılmaz. Devam eden oturum son canla biter —
        // o kontrol SubmitAnswer'da değil burada olmalı ki kullanıcı yarım
        // kalmış bir tura düşmesin.
        if ($consumesHearts && $this->hearts->balanceFor($command->userId)->isDepleted()) {
            throw LearningException::heartsDepleted(
                $this->hearts->balanceFor($command->userId)->nextHeartAt,
            );
        }

        $rule = SelectionRule::fromArray($node->selection_rule ?? []);

        $selection = $this->selectors->for($rule->mode)->select($rule, new SelectionContext(
            unitId: $unit->id,
            unitTopicIds: $this->unitTopics->topicIdsForUnit($unit->id),
            courseScope: $unit->course->scope->value,
            userId: $command->userId,
            excludeExerciseIds: [],
        ));

        if ($selection->exercises === []) {
            throw LearningException::poolInsufficient($node->exercise_count, 0);
        }

        $previous = UserNodeProgress::query()
            ->where('user_id', $command->userId)
            ->where('unit_node_id', $node->id)
            ->first();

        return DB::transaction(function () use ($command, $node, $unit, $selection, $consumesHearts, $previous): StudySession {
            $now = $this->clock->now();

            $session = StudySession::query()->create([
                'user_id' => $command->userId,
                'unit_node_id' => $node->id,
                'course_id' => $unit->course_id,
                'unit_id' => $unit->id,
                'status' => 'active',
                'consumes_hearts' => $consumesHearts,
                'time_limit_sec' => $node->time_limit_sec,
                'is_replay' => (bool) $previous?->completed,
                'started_at' => $now,
                'expires_at' => $now->add(new DateInterval(
                    'PT'.(int) config('tekrarla.learning.session_ttl_minutes', 60).'M'
                )),
                'idempotency_key' => $command->idempotencyKey,
            ]);

            $this->snapshotExercises($session, $selection->exercises);

            return $session->load('items');
        });
    }

    /** @param  list<ExerciseRef>  $refs */
    private function snapshotExercises(StudySession $session, array $refs): void
    {
        $exercises = Exercise::query()
            ->whereIn('id', array_column($refs, 'id'))
            ->get()
            ->keyBy('id');

        $position = 1;

        foreach ($refs as $ref) {
            $exercise = $exercises->get($ref->id);

            if ($exercise === null) {
                continue;
            }

            $session->items()->create([
                'exercise_id' => $exercise->id,
                'topic_id' => $exercise->topic_id,
                'position' => $position++,
                'type' => $exercise->getRawOriginal('type'),
                'exercise_version' => $exercise->version,
                'content_snapshot' => $exercise->content,
                'answer_key_snapshot' => $exercise->answer_key,
                'explanation_snapshot' => $exercise->explanation,
            ]);
        }
    }
}
