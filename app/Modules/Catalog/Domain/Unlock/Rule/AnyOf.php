<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Unlock\Rule;

use App\Modules\Catalog\Domain\Unlock\UnlockContext;
use App\Modules\Catalog\Domain\Unlock\UnlockRule;
use App\Modules\Catalog\Domain\Unlock\UnlockVerdict;

final readonly class AnyOf implements UnlockRule
{
    /** @param  list<UnlockRule>  $rules */
    public function __construct(private array $rules) {}

    public function evaluate(UnlockContext $context): UnlockVerdict
    {
        $lastVerdict = null;

        foreach ($this->rules as $rule) {
            $verdict = $rule->evaluate($context);

            if ($verdict->satisfied) {
                return $verdict;
            }

            $lastVerdict = $verdict;
        }

        return $lastVerdict ?? UnlockVerdict::unlocked();
    }
}
