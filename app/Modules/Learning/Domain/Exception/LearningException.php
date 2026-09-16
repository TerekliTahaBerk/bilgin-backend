<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Exception;

use DateTimeImmutable;
use RuntimeException;

/**
 * Oyun döngüsünün beklenen hataları.
 *
 * Her biri API sözleşmesindeki bir hata KODUNA karşılık gelir; istemci koda
 * göre dallanır (can bitti modalı, kilit uyarısı...), metne göre değil.
 */
final class LearningException extends RuntimeException
{
    /** @param  array<string, mixed>  $details */
    private function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function heartsDepleted(?DateTimeImmutable $nextHeartAt): self
    {
        return new self(
            'HEARTS_DEPLETED',
            'Canların bitti. Biraz sonra yeniden deneyebilirsin.',
            409,
            ['next_heart_at' => $nextHeartAt?->format(DATE_ATOM)],
        );
    }

    public static function nodeLocked(string $reason): self
    {
        return new self('NODE_LOCKED', 'Bu adım henüz açılmadı.', 409, ['lock_reason' => $reason]);
    }

    public static function sessionNotFound(): self
    {
        return new self('SESSION_NOT_FOUND', 'Oturum bulunamadı.', 404);
    }

    public static function sessionAlreadyCompleted(): self
    {
        return new self('SESSION_ALREADY_COMPLETED', 'Bu tur zaten tamamlandı.', 409);
    }

    public static function sessionExpired(): self
    {
        return new self('SESSION_EXPIRED', 'Turun süresi doldu.', 410);
    }

    public static function exerciseNotInSession(): self
    {
        return new self('EXERCISE_NOT_IN_SESSION', 'Bu soru bu tura ait değil.', 422);
    }

    public static function poolInsufficient(int $required, int $available): self
    {
        return new self(
            'POOL_INSUFFICIENT',
            'Bu adım için yeterli soru bulunamadı.',
            503,
            ['required' => $required, 'available' => $available],
        );
    }

    public static function premiumRequired(): self
    {
        return new self('PREMIUM_REQUIRED', 'Bu içerik Premium ile açılır.', 402);
    }
}
