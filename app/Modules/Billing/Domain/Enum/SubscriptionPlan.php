<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Enum;

enum SubscriptionPlan: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return $this === self::Monthly ? 'Aylık' : 'Yıllık';
    }
}
