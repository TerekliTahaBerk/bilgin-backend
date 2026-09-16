<?php

declare(strict_types=1);

namespace App\Modules\League\Application\UseCase;

final readonly class StandingMember
{
    public function __construct(
        public int $rank,
        public int $userId,
        public ?string $name,
        public ?string $avatarKey,
        public int $weeklyXp,
        public int $streak,
        public bool $isMe,
    ) {}
}
