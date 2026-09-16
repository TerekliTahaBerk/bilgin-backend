<?php

declare(strict_types=1);

namespace App\Shared\Domain\Learner;

/**
 * Öğrencinin diğer modüllerin ihtiyaç duyduğu asgari profili.
 *
 * Catalog ve Curriculum, Identity'nin User modeline DOKUNMAZ; bu DTO'yu
 * okur. Böylece "kullanıcı" kavramı modüller arasında sızmaz ve Identity'nin
 * iç yapısı değiştiğinde diğer modüller etkilenmez.
 */
final readonly class LearnerProfile
{
    public function __construct(
        public int $userId,
        public ?int $gradeLevel = null,
        public ?int $targetExamYear = null,
        public string $timezone = 'Europe/Istanbul',
        public ?int $primaryExamVariantId = null,
    ) {}

    public function hasEnrollment(): bool
    {
        return $this->primaryExamVariantId !== null;
    }
}
