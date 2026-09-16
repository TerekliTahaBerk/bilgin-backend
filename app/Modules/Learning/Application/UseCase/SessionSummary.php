<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\UseCase;

use App\Modules\Gamification\Application\UseCase\XpOutcome;
use App\Modules\Gamification\Infrastructure\Eloquent\Model\Badge;
use App\Modules\Learning\Domain\Scoring\ExamScore;
use App\Modules\Learning\Infrastructure\Eloquent\Model\StudySession;
use App\Shared\Domain\Hearts\HeartBalance;

/** Sonuç ekranının tamamı. */
final readonly class SessionSummary
{
    public function __construct(
        public StudySession $session,
        public int $correct,
        public int $total,
        public int $accuracy,
        public bool $isPerfect,
        public bool $meetsThreshold,
        public XpOutcome $xp,
        public ProgressOutcome $progress,
        public HeartBalance $hearts,
        public ?ExamScore $examScore = null,
        /** @var list<Badge> */
        public array $newBadges = [],
    ) {}

    public function isExamSimulation(): bool
    {
        return $this->examScore !== null;
    }
}
