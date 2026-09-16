<?php

declare(strict_types=1);

namespace App\Shared\Domain\Learner;

/**
 * Öğrenme tarafının rozet değerlendirmesi için gereken özet.
 *
 * Gamification, Learning'in tablolarına DOKUNMAZ; bu DTO'yu okur. Rozet
 * kriterleri iki modülün verisini birleştiriyor ama ikisi birbirini tanımıyor.
 */
final readonly class LearningStats
{
    public function __construct(
        public int $completedUnits = 0,
        public int $completedNodes = 0,
        public int $maxCourseLevel = 0,
        public int $strongTopics = 0,
        public int $totalStudySeconds = 0,
    ) {}
}
