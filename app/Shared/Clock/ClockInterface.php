<?php

declare(strict_types=1);

namespace App\Shared\Clock;

use DateTimeImmutable;

/**
 * Zamanı enjekte edilebilir kılar.
 *
 * Can rejenerasyonu, seri hesabı ve lig haftası "şu an"a bağlıdır; bunları
 * now() çağırarak yazmak testi imkânsız kılar. Domain sınıfları daima bu
 * arayüzü alır, asla Carbon::now() çağırmaz.
 */
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
