<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\UseCase;

use App\Modules\Catalog\Domain\Contract\BlueprintReader;
use App\Modules\Catalog\Domain\Enum\SelectionMode;
use App\Modules\Catalog\Domain\Selection\SelectionContext;
use App\Modules\Catalog\Domain\Selection\SelectionRule;
use App\Modules\Catalog\Domain\Selection\SelectorRegistry;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Exercise;
use App\Modules\Learning\Domain\Exception\LearningException;
use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use App\Shared\Clock\ClockInterface;
use DateInterval;
use Illuminate\Support\Facades\DB;

/**
 * Deneme (sınav provası) başlatır.
 *
 * Normal turdan üç farkı var ve üçü de bilinçli:
 *   1. CAN HARCAMAZ — gerçek sınav havası bozulmasın; yanlış yapmak
 *      cezalandırılmaz, ölçülür.
 *   2. Tek üniteye bağlı değil — sorular blueprint'e göre birden çok dersten.
 *   3. Sonucu doğruluk değil NET ve tahmini puan.
 *
 * StartStudySession'dan ayrı bir sınıf olmasının sebebi bu üç fark:
 * tek sınıfta birleştirmek, her adımda "deneme mi?" diye dallanmak olurdu.
 */
final readonly class StartExamSimulation
{
    public function __construct(
        private ClockInterface $clock,
        private SelectorRegistry $selectors,
        private BlueprintReader $blueprints,
    ) {}

    public function __invoke(int $userId, int $blueprintId, ?string $idempotencyKey = null): StudySession
    {
        if ($idempotencyKey !== null) {
            $existing = StudySession::query()->where('idempotency_key', $idempotencyKey)->with('items')->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $blueprint = $this->blueprints->find($blueprintId)
            ?? throw LearningException::sessionNotFound();

        $selection = $this->selectors->for(SelectionMode::Blueprint)->select(
            SelectionRule::fromArray(['mode' => 'blueprint', 'blueprint_id' => $blueprintId]),
            new SelectionContext(unitId: 0, unitTopicIds: [], courseScope: 'tyt', userId: $userId),
        );

        if ($selection->exercises === []) {
            throw LearningException::poolInsufficient($blueprint->totalQuestions(), 0);
        }

        return DB::transaction(function () use ($userId, $blueprint, $selection, $idempotencyKey): StudySession {
            $now = $this->clock->now();

            $session = StudySession::query()->create([
                'user_id' => $userId,
                'exam_blueprint_id' => $blueprint->id,
                'status' => 'active',
                'consumes_hearts' => false,
                'time_limit_sec' => $blueprint->durationMin * 60,
                'started_at' => $now,
                // Deneme uzun sürer; oturum TTL'i sınav süresinin iki katı
                // olmalı ki mola veren öğrencinin turu düşmesin.
                'expires_at' => $now->add(new DateInterval('PT'.($blueprint->durationMin * 2).'M')),
                'idempotency_key' => $idempotencyKey,
            ]);

            $exercises = Exercise::query()
                ->whereIn('id', array_column($selection->exercises, 'id'))
                ->get()
                ->keyBy('id');

            $position = 1;

            foreach ($selection->exercises as $ref) {
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

            return $session->load('items');
        });
    }
}
