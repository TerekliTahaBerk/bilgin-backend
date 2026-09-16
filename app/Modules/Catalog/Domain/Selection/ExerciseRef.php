<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Selection;

/** Havuzun döndürdüğü hafif referans. Soru gövdesi oturum kurulurken hidrate edilir. */
final readonly class ExerciseRef
{
    public function __construct(
        public int $id,
        public string $uuid,
        public string $type,
        public int $difficulty,
        public int $topicId,
        public int $version,
    ) {}
}
