<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\DTO;

use App\Modules\Billing\Domain\Enum\SubscriptionPlan;
use App\Modules\Billing\Domain\Enum\SubscriptionStatus;
use DateTimeImmutable;

/** Sağlayıcıdan bağımsız abonelik olayı. */
final readonly class SubscriptionEvent
{
    public function __construct(
        public string $providerEventId,
        public string $type,
        public string $appUserId,
        public string $productId,
        public SubscriptionPlan $plan,
        public SubscriptionStatus $status,
        public ?string $store,
        public ?string $providerSubscriptionId,
        public DateTimeImmutable $occurredAt,
        public ?DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $trialEnd,
    ) {}
}
