<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Unlock\Rule;

use App\Modules\Catalog\Domain\Unlock\LockReason;
use App\Modules\Catalog\Domain\Unlock\UnlockContext;
use App\Modules\Catalog\Domain\Unlock\UnlockRule;
use App\Modules\Catalog\Domain\Unlock\UnlockVerdict;

/** Ünite Challenge'ın kilidi: ünitedeki tüm çalışma node'ları bitmeli. */
final readonly class UnitStudyNodesCompleted implements UnlockRule
{
    public function evaluate(UnlockContext $context): UnlockVerdict
    {
        return $context->progress->hasCompletedAll($context->unitStudyNodeIds)
            ? UnlockVerdict::unlocked()
            : UnlockVerdict::locked(LockReason::UnitIncomplete);
    }
}
