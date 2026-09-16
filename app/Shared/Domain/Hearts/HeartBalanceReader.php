<?php

declare(strict_types=1);

namespace App\Shared\Domain\Hearts;

/** Yalnızca okuma isteyen taraflar için (profil, oturum yanıtı). */
interface HeartBalanceReader
{
    public function balanceFor(int $userId): HeartBalance;
}
