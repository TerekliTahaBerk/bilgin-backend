<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Unlock\Rule;

use App\Modules\Catalog\Domain\Unlock\LockReason;
use App\Modules\Catalog\Domain\Unlock\UnlockContext;
use App\Modules\Catalog\Domain\Unlock\UnlockRule;
use App\Modules\Catalog\Domain\Unlock\UnlockVerdict;

final readonly class PreviousNodeCompleted implements UnlockRule
{
    public function evaluate(UnlockContext $context): UnlockVerdict
    {
        if ($context->previousNodeId === null) {
            return UnlockVerdict::unlocked();
        }

        return $context->progress->hasCompletedNode($context->previousNodeId)
            ? UnlockVerdict::unlocked()
            : UnlockVerdict::locked(LockReason::PreviousNodeIncomplete);
    }
}
