<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\UseCase;

final readonly class ProgressOutcome
{
    /** @param  list<int>  $unlockedNodeIds */
    public function __construct(
        public bool $isFirstCompletion,
        public bool $nodeCompleted,
        public int $unitCompletionPercent,
        /**
         * Bu turdan ÖNCEKİ ünite yüzdesi.
         *
         * Tur sonu ekranı "%35 → %46" diyor. Tek sayı göstermek, kullanıcıya
         * o turda ne kadar ilerlediğini değil yalnızca nerede olduğunu
         * söylerdi; ilerlemenin görünmesi kutlamanın asıl konusu.
         */
        public int $unitCompletionPercentBefore,
        public bool $unitCompleted,
        public array $unlockedNodeIds,
    ) {}
}
