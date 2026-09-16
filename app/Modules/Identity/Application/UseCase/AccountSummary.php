<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCase;

/** Çakışma ekranında kullanıcıya gösterilen özet — hangi ilerleme kaybolacak? */
final readonly class AccountSummary
{
    public function __construct(
        public string $uuid,
        public ?string $name,
        public bool $isGuest,
        public int $totalXp,
        public int $level,
        public int $currentStreak,
    ) {}
}
