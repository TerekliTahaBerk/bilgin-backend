<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\DTO;

use App\Modules\Curriculum\Domain\Enum\FieldCode;
use App\Modules\Identity\Domain\Enum\DailyGoal;
use App\Modules\Identity\Domain\Enum\Grade;

final readonly class OnboardingCommand
{
    public function __construct(
        public int $userId,
        public string $examCode,
        public FieldCode $field,
        public ?Grade $grade = null,
        public ?int $targetExamYear = null,
        public ?int $targetExamMonth = null,
        public ?string $name = null,
        public ?string $avatarKey = null,
        public ?string $acquisitionSource = null,
        public DailyGoal $dailyGoal = DailyGoal::Duzenli,
        public bool $reminderEnabled = true,
        public ?string $reminderTime = null,
    ) {}
}
