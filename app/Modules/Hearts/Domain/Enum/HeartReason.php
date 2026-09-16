<?php

declare(strict_types=1);

namespace App\Modules\Hearts\Domain\Enum;

enum HeartReason: string
{
    case WrongAnswer = 'wrong_answer';
    case Regen = 'regen';
    case AdReward = 'ad_reward';
    case PracticeRefill = 'practice_refill';
    case Premium = 'premium';
    case AdminGrant = 'admin_grant';
}
