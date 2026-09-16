<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Unlock\Rule;

use App\Modules\Catalog\Domain\Unlock\LockReason;
use App\Modules\Catalog\Domain\Unlock\UnlockContext;
use App\Modules\Catalog\Domain\Unlock\UnlockRule;
use App\Modules\Catalog\Domain\Unlock\UnlockVerdict;

/**
 * Bir önceki node'da minimum doğruluk şartı.
 *
 * Önceki node hiç oynanmadıysa bu kural İHLAL sayılmaz — sıra kuralı
 * PreviousNodeCompleted'ın işidir. Tek kural tek sorumluluk (SRP).
 */
final readonly class MinAccuracy implements UnlockRule
{
    public function __construct(private int $threshold) {}

    public function evaluate(UnlockContext $context): UnlockVerdict
    {
        if (! $context->progress->hasCompletedNode($context->previousNodeId)) {
            return UnlockVerdict::unlocked();
        }

        return $context->progress->accuracyFor($context->previousNodeId) >= $this->threshold
            ? UnlockVerdict::unlocked()
            : UnlockVerdict::locked(LockReason::AccuracyTooLow);
    }
}
