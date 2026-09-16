<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Unlock\Rule;

use App\Modules\Catalog\Domain\Unlock\UnlockContext;
use App\Modules\Catalog\Domain\Unlock\UnlockRule;
use App\Modules\Catalog\Domain\Unlock\UnlockVerdict;

final readonly class AllOf implements UnlockRule
{
    /** @param  list<UnlockRule>  $rules */
    public function __construct(private array $rules) {}

    public function evaluate(UnlockContext $context): UnlockVerdict
    {
        foreach ($this->rules as $rule) {
            $verdict = $rule->evaluate($context);

            // İlk ihlal edilen kuralın sebebi döner: kullanıcıya gösterilecek
            // mesaj "neden kilitli" sorusunun en yakın cevabı olmalı.
            if (! $verdict->satisfied) {
                return $verdict;
            }
        }

        return UnlockVerdict::unlocked();
    }
}
