<?php

declare(strict_types=1);

namespace App\Shared\Domain\Hearts;

/**
 * Can harcayan taraflar için. Okuma arayüzünden AYRI tutuluyor (ISP):
 * profil ekranı can harcayamamalı, tip sistemi bunu garanti etsin.
 */
interface HeartConsumer
{
    /**
     * @param  string|null  $referenceKey  idempotency anahtarı — aynı cevap
     *                                     iki kez can düşüremez
     */
    public function consume(int $userId, ?string $referenceKey = null): HeartBalance;
}
