<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Unlock;

/**
 * İstemciye kod olarak gider, metin olarak değil. Metni uygulama gösterir —
 * böylece metin değişikliği sunucu deploy'u gerektirmez ve çeviri istemcide kalır.
 */
enum LockReason: string
{
    case PreviousNodeIncomplete = 'PREVIOUS_NODE_INCOMPLETE';
    case PreviousUnitIncomplete = 'PREVIOUS_UNIT_INCOMPLETE';
    case UnitIncomplete = 'UNIT_INCOMPLETE';
    case AccuracyTooLow = 'ACCURACY_TOO_LOW';
    case PremiumRequired = 'PREMIUM_REQUIRED';
}
