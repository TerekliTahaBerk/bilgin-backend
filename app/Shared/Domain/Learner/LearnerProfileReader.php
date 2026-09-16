<?php

declare(strict_types=1);

namespace App\Shared\Domain\Learner;

interface LearnerProfileReader
{
    public function for(int $userId): LearnerProfile;
}
