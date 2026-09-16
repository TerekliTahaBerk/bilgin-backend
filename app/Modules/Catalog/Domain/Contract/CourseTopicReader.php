<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Contract;

interface CourseTopicReader
{
    /**
     * Dersin ünitelerinde geçen tüm konular.
     *
     * @return list<int>
     */
    public function topicIdsForCourse(int $courseId): array;
}
