<?php

declare(strict_types=1);

namespace App\Modules\Gamification\Application\DTO;

/**
 * XP hesabının ihtiyaç duyduğu oturum bilgisi.
 *
 * Gamification, Learning'in StudySession modelini ALMAZ — yalnızca bu beş
 * alanı. Model geçirmek, XP hesabını oturum tablosunun şemasına bağlar ve
 * Learning'de yapılan her değişikliği Gamification'ın da bilmesini gerektirirdi.
 */
final readonly class SessionXpContext
{
    public function __construct(
        public int $userId,
        public int $sessionId,
        public ?int $courseId,
        public bool $isReplay,
        public int $correctCount,
    ) {}
}
