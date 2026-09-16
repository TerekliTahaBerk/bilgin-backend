<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\UseCase;

use App\Modules\Catalog\Domain\Enum\NodeType;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Modules\Learning\Infrastructure\Eloquent\Model\ReviewQueueItem;
use App\Modules\Learning\Infrastructure\Eloquent\Model\SessionItem;
use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use App\Modules\Learning\Infrastructure\Eloquent\Model\UserCourseProgress;
use App\Modules\Learning\Infrastructure\Eloquent\Model\UserNodeProgress;
use App\Modules\Learning\Infrastructure\Eloquent\Model\UserTopicStat;
use App\Modules\Learning\Infrastructure\Eloquent\Model\UserUnitProgress;
use App\Shared\Clock\ClockInterface;
use DateInterval;
use Illuminate\Support\Facades\DB;

/**
 * Oturum sonucunu tüm ilerleme tablolarına yayar.
 *
 * Sıra aşağıdan yukarı: node → ünite → ders. Her seviye bir altındakinden
 * TÜRETİLİR, ayrı ayrı sayılmaz; böylece tutarsızlık (ünite %100 ama node
 * eksik gibi) yapısal olarak imkânsız.
 */
final readonly class UpdateProgress
{
    public function __construct(private ClockInterface $clock) {}

    public function __invoke(
        StudySession $session,
        UnitNode $node,
        int $accuracy,
        bool $isPerfect,
        bool $meetsThreshold,
    ): ProgressOutcome {
        $now = $this->clock->now();

        $nodeProgress = UserNodeProgress::query()->firstOrNew([
            'user_id' => $session->user_id,
            'unit_node_id' => $node->id,
        ]);

        $wasCompleted = (bool) $nodeProgress->completed;

        $nodeProgress->fill([
            'unit_id' => $session->unit_id,
            'course_id' => $session->course_id,
            'completed' => $wasCompleted || $meetsThreshold,
            'best_accuracy' => max((int) $nodeProgress->best_accuracy, $accuracy),
            'attempts' => (int) $nodeProgress->attempts + 1,
            'is_perfect' => (bool) $nodeProgress->is_perfect || $isPerfect,
            'completed_at' => $nodeProgress->completed_at ?? ($meetsThreshold ? $now : null),
            'last_attempt_at' => $now,
        ])->save();

        $unit = $this->recalculateUnit($session);
        $this->recalculateCourse($session);
        $this->updateTopicStats($session);
        $this->enqueueWrongAnswers($session);

        return new ProgressOutcome(
            isFirstCompletion: ! $wasCompleted && $meetsThreshold,
            nodeCompleted: $meetsThreshold,
            unitCompletionPercent: $unit->completion_percent,
            unitCompleted: $unit->completed_at !== null,
            unlockedNodeIds: $this->unlockedNodes($session, $node, $meetsThreshold),
        );
    }

    /**
     * Deneme oturumu: yol ilerlemesi YOK, ölçüm VAR.
     *
     * Deneme bir üniteye ait olmadığı için node/ünite/ders ilerlemesi
     * güncellenmez. Ama konu analizi ve yanlış kuyruğu denemeden beslenmeli:
     * öğrencinin zayıf konusunu en net gösteren şey zaten denemedir.
     */
    public function forExamSimulation(StudySession $session): ProgressOutcome
    {
        $this->updateTopicStats($session);
        $this->enqueueWrongAnswers($session);

        return new ProgressOutcome(
            isFirstCompletion: false,
            nodeCompleted: false,
            unitCompletionPercent: 0,
            unitCompleted: false,
            unlockedNodeIds: [],
        );
    }

    /** Ünite yüzdesi node'lardan sayılır — ayrı sayaç tutulmaz. */
    private function recalculateUnit(StudySession $session): UserUnitProgress
    {
        $totalNodes = UnitNode::query()
            ->where('unit_id', $session->unit_id)
            ->where('status', 'published')
            ->count();

        $completedNodes = UserNodeProgress::query()
            ->where('user_id', $session->user_id)
            ->where('unit_id', $session->unit_id)
            ->where('completed', true)
            ->count();

        $percent = $totalNodes === 0 ? 0 : (int) round($completedNodes / $totalNodes * 100);

        $progress = UserUnitProgress::query()->firstOrNew([
            'user_id' => $session->user_id,
            'unit_id' => $session->unit_id,
        ]);

        $progress->fill([
            'course_id' => $session->course_id,
            'completion_percent' => $percent,
            'completed_nodes' => $completedNodes,
            'total_nodes' => $totalNodes,
            'completed_at' => $progress->completed_at
                ?? ($totalNodes > 0 && $completedNodes === $totalNodes ? $this->clock->now() : null),
        ])->save();

        return $progress;
    }

    private function recalculateCourse(StudySession $session): void
    {
        $totalUnits = DB::table('units')
            ->where('course_id', $session->course_id)
            ->where('status', 'published')
            ->count();

        $completedUnits = UserUnitProgress::query()
            ->where('user_id', $session->user_id)
            ->where('course_id', $session->course_id)
            ->whereNotNull('completed_at')
            ->count();

        $xp = (int) DB::table('xp_ledger')
            ->where('user_id', $session->user_id)
            ->where('course_id', $session->course_id)
            ->sum('amount');

        UserCourseProgress::query()->updateOrCreate(
            ['user_id' => $session->user_id, 'course_id' => $session->course_id],
            [
                'xp' => $xp,
                'completed_units' => $completedUnits,
                'total_units' => $totalUnits,
                'last_studied_at' => $this->clock->now(),
            ],
        );
    }

    /**
     * Konu ustalığı. topic_id subject seviyesinde olduğu için TYT'de
     * öğrenilen bir konu AYT'de de "güçlü" görünür — öğrenci aynı şeyi
     * iki kez öğrenmiş sayılmaz.
     */
    private function updateTopicStats(StudySession $session): void
    {
        $perTopic = $session->items()
            ->whereNotNull('answered_at')
            ->get()
            ->groupBy('topic_id');

        foreach ($perTopic as $topicId => $items) {
            $stat = UserTopicStat::query()->firstOrNew([
                'user_id' => $session->user_id,
                'topic_id' => (int) $topicId,
            ]);

            $attempts = (int) $stat->attempts + $items->count();
            $correct = (int) $stat->correct + $items->filter(
                fn (SessionItem $i): bool => (float) $i->partial_score >= 1.0
            )->count();

            $accuracy = $attempts === 0 ? 0 : (int) round($correct / $attempts * 100);

            $stat->fill([
                'attempts' => $attempts,
                'correct' => $correct,
                'accuracy' => $accuracy,
                'mastery' => match (true) {
                    $accuracy >= 80 => 'strong',
                    $accuracy >= 50 => 'developing',
                    default => 'weak',
                },
                'last_practiced_at' => $this->clock->now(),
            ])->save();
        }
    }

    /** Yanlışlar "Hızlı Tekrar" kuyruğuna düşer; doğrular kuyruktan çıkar. */
    private function enqueueWrongAnswers(StudySession $session): void
    {
        $intervalDays = (int) config('tekrarla.learning.review_interval_days', 3);
        $dueAt = $this->clock->now()->add(new DateInterval("P{$intervalDays}D"));

        foreach ($session->items()->whereNotNull('answered_at')->get() as $item) {
            $key = ['user_id' => $session->user_id, 'exercise_id' => $item->exercise_id];

            if ((float) $item->partial_score >= 1.0) {
                ReviewQueueItem::query()->where($key)->delete();

                continue;
            }

            $existing = ReviewQueueItem::query()->where($key)->first();

            ReviewQueueItem::query()->updateOrCreate($key, [
                'topic_id' => $item->topic_id,
                'unit_id' => $session->unit_id,
                'due_at' => $dueAt,
                'interval_days' => $intervalDays,
                'lapses' => (int) ($existing->lapses ?? 0) + 1,
            ]);
        }
    }

    /**
     * Bu turun açtığı node'lar. Sonuç ekranındaki "Yeni node açıldı"
     * animasyonu buradan beslenir.
     *
     * @return list<int>
     */
    private function unlockedNodes(StudySession $session, UnitNode $node, bool $completed): array
    {
        if (! $completed) {
            return [];
        }

        $next = UnitNode::query()
            ->where('unit_id', $session->unit_id)
            ->where('status', 'published')
            ->where('sort_order', '>', $node->sort_order)
            ->orderBy('sort_order')
            ->first();

        $unlocked = $next !== null ? [(int) $next->id] : [];

        // Ünitenin tüm çalışma node'ları bittiyse Ünite Challenge da açılır.
        $challenge = UnitNode::query()
            ->where('unit_id', $session->unit_id)
            ->where('status', 'published')
            ->where('node_type', NodeType::UnitChallenge->value)
            ->first();

        // Az önce bitirilen node kendini "açıldı" diye raporlamamalı.
        if ($challenge !== null
            && (int) $challenge->id !== (int) $node->id
            && ! in_array((int) $challenge->id, $unlocked, true)) {
            $studyNodeIds = UnitNode::query()
                ->where('unit_id', $session->unit_id)
                ->where('status', 'published')
                ->whereNotIn('node_type', [NodeType::UnitChallenge->value, NodeType::ExamSim->value])
                ->pluck('id');

            $completedCount = UserNodeProgress::query()
                ->where('user_id', $session->user_id)
                ->whereIn('unit_node_id', $studyNodeIds)
                ->where('completed', true)
                ->count();

            if ($completedCount === $studyNodeIds->count()) {
                $unlocked[] = (int) $challenge->id;
            }
        }

        return $unlocked;
    }
}
