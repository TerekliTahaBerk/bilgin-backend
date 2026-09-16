<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Enum;

enum SocialProvider: string
{
    case Apple = 'apple';
    case Google = 'google';

    /**
     * Apple Sign-In App Store kuralı gereği ZORUNLU: başka bir sosyal giriş
     * sunan uygulama Apple'ı da sunmak zorunda, yoksa inceleme reddedilir.
     */
    public function isRequiredByStore(): bool
    {
        return $this === self::Apple;
    }
}
