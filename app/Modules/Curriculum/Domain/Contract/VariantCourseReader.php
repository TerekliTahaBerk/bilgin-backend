<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Domain\Contract;

use App\Modules\Curriculum\Domain\ReadModel\VariantCourseRow;

interface VariantCourseReader
{
    /** @return list<VariantCourseRow> sıralı ders satırları */
    public function coursesFor(int $examVariantId): array;
}
