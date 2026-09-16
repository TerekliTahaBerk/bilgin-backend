<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCase;

use App\Modules\Catalog\Domain\Contract\ProgressReader;
use App\Modules\Catalog\Domain\Enum\NodeType;
use App\Modules\Catalog\Domain\Path\LearnerContext;
use App\Modules\Catalog\Domain\Path\PathStrategyResolver;
use App\Modules\Catalog\Domain\Path\UnitCard;
use App\Modules\Catalog\Domain\Unlock\LockReason;
use App\Modules\Catalog\Domain\Unlock\ProgressSnapshot;
use App\Modules\Catalog\Domain\Unlock\UnlockContext;
use App\Modules\Catalog\Domain\Unlock\UnlockRuleFactory;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Unit;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\UnitNode;
use App\Shared\Domain\Enum\AccessLevel;
use App\Shared\Domain\Enum\PublishStatus;
use Illuminate\Support\Collection;

/**
 * "Ünite yolu" ekranı (tasarımın imza ekranı).
 *
 * Üç ayrı karar burada birleşir ama hiçbiri burada VERİLMEZ:
 *   - sıralama  → PathStrategy   (öğrencinin sınıfına göre)
 *   - kilit     → UnlockRule     (içerik ekibinin kurguladığı JSON'dan)
 *   - ilerleme  → ProgressReader (tek sorguluk snapshot)
 *
 * Bu sınıfın işi orkestrasyon; iş kuralı domain'de. Yeni bir sıralama
 * mantığı veya kilit türü eklendiğinde bu dosya açılmaz.
 */
final readonly class GetCoursePath
{
    public function __construct(
        private ProgressReader $progressReader,
        private UnlockRuleFactory $unlockRules,
        private PathStrategyResolver $pathStrategies,
    ) {}

    public function __invoke(Course $course, int $userId, LearnerContext $learner): CoursePathView
    {
        $units = $course->units()
            ->where('status', PublishStatus::Published)
            ->with(['nodes' => fn ($q) => $q->where('status', PublishStatus::Published)])
            ->get();

        $strategy = $this->pathStrategies->resolve($learner);

        /** @var array<int, Unit> $byId */
        $byId = [];
        $cards = [];

        foreach ($units as $unit) {
            $byId[$unit->id] = $unit;
            $cards[] = new UnitCard($unit->id, $unit->sort_order, $unit->grade_level);
        }

        $orderedIds = $strategy->order($cards, $learner);
        $progress = $this->progressReader->snapshotForCourse($userId, $course->id);

        $unitViews = [];
        $previousUnitId = null;

        foreach ($orderedIds as $unitId) {
            $unit = $byId[$unitId];
            $unitViews[] = $this->buildUnitView($unit, $previousUnitId, $progress, $learner);
            $previousUnitId = $unit->id;
        }

        return new CoursePathView(
            course: $course,
            pathStrategy: $strategy->name(),
            units: $unitViews,
        );
    }

    private function buildUnitView(
        Unit $unit,
        ?int $previousUnitId,
        ProgressSnapshot $progress,
        LearnerContext $learner,
    ): UnitPathView {
        /** @var Collection<int, UnitNode> $nodes */
        $nodes = $unit->nodes->sortBy('sort_order')->values();

        // Ünite Challenge'ın beklediği "çalışma node'ları": challenge olmayanlar.
        $studyNodeIds = [];

        foreach ($nodes as $candidate) {
            if (! in_array($candidate->node_type, [NodeType::UnitChallenge, NodeType::ExamSim], true)) {
                $studyNodeIds[] = (int) $candidate->id;
            }
        }

        $nodeViews = [];
        $previousNodeId = null;
        $completedCount = 0;

        foreach ($nodes as $node) {
            $requiresPremium = $node->access === AccessLevel::Premium || $unit->access === AccessLevel::Premium;

            $verdict = $this->unlockRules->make($node->unlock_rule)->evaluate(new UnlockContext(
                nodeId: $node->id,
                unitId: $unit->id,
                previousNodeId: $previousNodeId,
                previousUnitId: $previousUnitId,
                unitStudyNodeIds: $studyNodeIds,
                progress: $progress,
                hasPremium: $learner->hasPremium,
                nodeRequiresPremium: $requiresPremium,
            ));

            $completed = $progress->hasCompletedNode($node->id);
            $completed && $completedCount++;

            $nodeViews[] = new NodePathView(
                node: $node,
                completed: $completed,
                unlocked: $verdict->satisfied && (! $requiresPremium || $learner->hasPremium),
                lockReason: $requiresPremium && ! $learner->hasPremium
                    ? LockReason::PremiumRequired
                    : $verdict->reason,
                bestAccuracy: $progress->accuracyFor($node->id),
            );

            $previousNodeId = $node->id;
        }

        return new UnitPathView(
            unit: $unit,
            nodes: $nodeViews,
            completedNodes: $completedCount,
            totalNodes: $nodes->count(),
        );
    }
}
