<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Unlock\Rule;

use App\Modules\Catalog\Domain\Unlock\UnlockContext;
use App\Modules\Catalog\Domain\Unlock\UnlockRule;
use App\Modules\Catalog\Domain\Unlock\UnlockVerdict;

/**
 * unlock_rule null olduğunda kullanılır (ünitenin ilk node'u).
 *
 * Null yerine gerçek bir kural nesnesi döndürmek, çağıran tarafın hiçbir
 * zaman "kural var mı" diye sormak zorunda kalmamasını sağlar (Null Object).
 */
final readonly class AlwaysUnlocked implements UnlockRule
{
    public function evaluate(UnlockContext $context): UnlockVerdict
    {
        return UnlockVerdict::unlocked();
    }
}
