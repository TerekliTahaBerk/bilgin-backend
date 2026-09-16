<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Domain\Xp;

/** XP hesabının girdisi. Eloquent tanımaz — saf veri. */
final readonly class SessionOutcome
{
    public function __construct(
        public int $baseXp,
        public int $accuracy,
        public bool $isPerfect,
        public bool $isFirstCompletion,
        public bool $isReplay,
        public bool $meetsCompletionThreshold,
    ) {}
}
