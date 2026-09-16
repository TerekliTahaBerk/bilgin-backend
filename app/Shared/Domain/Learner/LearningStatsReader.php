<?php

declare(strict_types=1);

namespace App\Shared\Domain\Learner;

interface LearningStatsReader
{
    public function statsFor(int $userId): LearningStats;
}
