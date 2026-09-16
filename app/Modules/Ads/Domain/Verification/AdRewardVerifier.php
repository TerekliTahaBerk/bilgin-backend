<?php

declare(strict_types=1);

namespace App\Modules\Ads\Domain\Verification;

/** Reklam ağının sunucu tarafı doğrulama (SSV) callback'ini doğrular. */
interface AdRewardVerifier
{
    /**
     * @param  array<string, mixed>  $query  callback'in sorgu parametreleri
     * @param  string  $rawQuery  ham sorgu dizesi — imza onun üzerinde hesaplanır
     *
     * @throws AdVerificationFailed
     */
    public function verify(array $query, string $rawQuery): AdReward;
}
