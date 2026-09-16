<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCase;

use App\Shared\Domain\Enum\AccessLevel;
use App\Shared\Domain\Enum\PublishStatus;

final readonly class CreateUnitFromTemplateCommand
{
    /** @param  list<int>  $topicIds */
    public function __construct(
        public string $courseCode,
        public string $templateCode,
        public string $title,
        public array $topicIds,
        public int $sortOrder = 1,
        public ?string $description = null,
        public ?int $gradeLevel = null,
        public ?string $difficultyBand = null,
        public AccessLevel $access = AccessLevel::Free,
        public ?int $estimatedMinutes = null,
        public PublishStatus $status = PublishStatus::Draft,
    ) {}
}
