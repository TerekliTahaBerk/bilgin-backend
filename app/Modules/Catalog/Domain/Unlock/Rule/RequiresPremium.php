<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Unlock\Rule;

use App\Modules\Catalog\Domain\Unlock\LockReason;
use App\Modules\Catalog\Domain\Unlock\UnlockContext;
use App\Modules\Catalog\Domain\Unlock\UnlockRule;
use App\Modules\Catalog\Domain\Unlock\UnlockVerdict;

final readonly class RequiresPremium implements UnlockRule
{
    public function evaluate(UnlockContext $context): UnlockVerdict
    {
        return $context->hasPremium
            ? UnlockVerdict::unlocked()
            : UnlockVerdict::locked(LockReason::PremiumRequired);
    }
}
