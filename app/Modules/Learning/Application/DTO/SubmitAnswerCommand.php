<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\DTO;

final readonly class SubmitAnswerCommand
{
    /** @param  array<string, mixed>  $answer */
    public function __construct(
        public int $userId,
        public string $sessionUuid,
        public int $exerciseId,
        public array $answer,
        public int $elapsedMs = 0,
    ) {}
}
