<?php

declare(strict_types=1);

namespace App\Modules\Hearts\Domain\Policy;

/**
 * Can harcama politikası.
 *
 * Bu arayüzün varlık sebebi: kod tabanının hiçbir yerinde
 * `if ($user->isPremium())` yazmamak. Premium kullanıcıda consume() no-op'tur
 * ve çağıran taraf farkı bilmez (LSP).
 */
interface HeartPolicy
{
    public function consumesHearts(): bool;

    public function maxHearts(int $configuredMax): int;
}
