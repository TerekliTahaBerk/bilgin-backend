<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\UseCase;

use App\Modules\Catalog\Domain\Contract\BlueprintReader;
use App\Modules\Catalog\Domain\Enum\DifficultyLevel;
use App\Modules\Catalog\Domain\Enum\NodeType;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Modules\Gamification\Application\DTO\SessionXpContext;
use App\Modules\Gamification\Application\UseCase\AwardSessionXp;
use App\Modules\Gamification\Application\UseCase\EvaluateBadges;
use App\Modules\Learning\Domain\Exception\LearningException;
use App\Modules\Learning\Domain\Scoring\ExamScore;
use App\Modules\Learning\Domain\Scoring\NetScoring;
use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use App\Shared\Clock\ClockInterface;
use App\Shared\Domain\Hearts\HeartBalanceReader;
use Illuminate\Support\Facades\DB;

/**
 * Turu kapatır ve sonuç ekranının TAMAMINI tek yanıtta üretir.
 *
 * Tasarımda bu ekran aynı anda şunları gösteriyor: XP dökümü, doğruluk,
 * seri animasyonu, level çubuğu, "yeni node açıldı" ve ünite yüzdesi.
 * Bunların her biri için ayrı istek atmak, kutlama animasyonunu ağ
 * gecikmesine bağımlı kılardı — o yüzden hepsi burada birleşiyor.
 */
final readonly class CompleteSession
{
    public function __construct(
        private ClockInterface $clock,
        private UpdateProgress $updateProgress,
        private AwardSessionXp $awardXp,
        private HeartBalanceReader $hearts,
        private BlueprintReader $blueprints,
        private EvaluateBadges $evaluateBadges,
    ) {}

    public function __invoke(int $userId, string $sessionUuid): SessionSummary
    {
        return DB::transaction(function () use ($userId, $sessionUuid): SessionSummary {
            $session = StudySession::query()
                ->where('uuid', $sessionUuid)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw LearningException::sessionNotFound();
            }

            if ($session->status === 'completed') {
                throw LearningException::sessionAlreadyCompleted();
            }

            $items = $session->items()->get();
            $answered = $items->filter->isAnswered();

            // Cevaplanmamış soru "yanlış" değil, "yok" sayılır: yarım bırakılan
            // turda kalan sorular doğruluk oranını aşağı çekmemeli.
            $total = $answered->count();
            $score = $answered->sum(fn ($item): float => (float) $item->partial_score);
            $accuracy = $total === 0 ? 0 : (int) round($score / $total * 100);

            // "Kusursuz" = tüm sorular cevaplanmış VE hepsi tam doğru.
            $isPerfect = $total > 0
                && $total === $items->count()
                && $answered->every(fn ($item): bool => (float) $item->partial_score >= 1.0);

            $threshold = (int) config('tekrarla.xp.completion_accuracy', 80);
            $meetsThreshold = $accuracy >= $threshold;

            $isExamSimulation = $session->exam_blueprint_id !== null;

            $examScore = $isExamSimulation
                ? $this->scoreExam($session, $items->count())
                : null;

            $session->fill([
                'status' => 'completed',
                'accuracy' => $accuracy,
                'is_perfect' => $isPerfect,
                'net' => $examScore?->net,
                'estimated_score' => $examScore?->estimatedScore,
                'completed_at' => $this->clock->now(),
            ])->save();

            if ($isExamSimulation) {
                // Denemede node/ünite ilerlemesi yok — ölçüm var, yol yok.
                // Konu analizi ve yanlış kuyruğu yine de beslenir.
                $progress = ($this->updateProgress)->forExamSimulation($session);
                $baseXp = DifficultyLevel::SinavProvasi->baseXp() * NodeType::ExamSim->xpMultiplier();
            } else {
                $node = UnitNode::query()->findOrFail($session->unit_node_id);
                $progress = ($this->updateProgress)($session, $node, $accuracy, $isPerfect, $meetsThreshold);
                $baseXp = (float) $node->xp_reward;
            }

            $xp = ($this->awardXp)(
                session: new SessionXpContext(
                    userId: $userId,
                    sessionId: (int) $session->id,
                    courseId: $session->course_id !== null ? (int) $session->course_id : null,
                    isReplay: (bool) $session->is_replay,
                    correctCount: (int) $session->correct_count,
                ),
                baseXp: (int) round($baseXp),
                accuracy: $accuracy,
                isPerfect: $isPerfect,
                isFirstCompletion: $progress->isFirstCompletion,
                meetsThreshold: $meetsThreshold,
            );

            $session->xp_awarded = $xp->award->total;
            $session->save();

            // Rozetler XP ve ilerleme yazıldıktan SONRA değerlendirilir;
            // "İlk Ünite" rozetinin o turda kazanılması buna bağlı.
            $newBadges = ($this->evaluateBadges)($userId);

            return new SessionSummary(
                session: $session,
                correct: $session->correct_count,
                total: $items->count(),
                accuracy: $accuracy,
                isPerfect: $isPerfect,
                meetsThreshold: $meetsThreshold,
                xp: $xp,
                progress: $progress,
                hearts: $this->hearts->balanceFor($userId),
                examScore: $examScore,
                newBadges: $newBadges,
            );
        });
    }

    /**
     * Denemenin neti ve tahmini puanı.
     *
     * Cevaplanmamış soru BOŞ sayılır, yanlış değil — gerçek sınavda da
     * boş bırakmak yanlıştan farklıdır ve net hesabını doğrudan etkiler.
     */
    private function scoreExam(StudySession $session, int $totalQuestions): ExamScore
    {
        $blueprint = $this->blueprints->find((int) $session->exam_blueprint_id);

        // Blueprint silinmişse ceza uygulanmaz: eksik yapılandırma yüzünden
        // öğrencinin neti düşürülmemeli.
        $rule = $blueprint === null
            ? new NetScoring(penaltyRatio: 0.0)
            : new NetScoring(penaltyRatio: $blueprint->penaltyRatio, baseScore: $blueprint->baseScore);

        return $rule->score(
            correct: (int) $session->correct_count,
            wrong: (int) $session->wrong_count,
            total: $totalQuestions,
        );
    }
}
