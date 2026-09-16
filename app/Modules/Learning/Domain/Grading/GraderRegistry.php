<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Grading;

use App\Modules\Catalog\Domain\Enum\ExerciseType;
use RuntimeException;

final class GraderRegistry
{
    /** @var array<string, ExerciseGrader> */
    private array $graders = [];

    /** @param  iterable<ExerciseGrader>  $graders */
    public function __construct(iterable $graders = [])
    {
        foreach ($graders as $grader) {
            $this->register($grader);
        }
    }

    public function register(ExerciseGrader $grader): void
    {
        $this->graders[$grader->type()->value] = $grader;
    }

    public function for(ExerciseType $type): ExerciseGrader
    {
        return $this->graders[$type->value]
            ?? throw new RuntimeException("Bu egzersiz tipi için grader yok: {$type->value}");
    }

    /** @return list<string> */
    public function supportedTypes(): array
    {
        return array_keys($this->graders);
    }
}
