<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Enum;

enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Grace = 'grace';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * Premium hakları hangi durumlarda açık?
     *
     * Grace (ödeme hatası) DAHİL: kartı geçmeyen kullanıcının erişimini anında
     * kesmek, çoğu zaman bankadan kaynaklanan bir sorun için müşteriyi
     * cezalandırmaktır ve iptal oranını artırır.
     *
     * Cancelled da dahil: iptal eden kullanıcı ödediği dönemin sonuna kadar
     * hizmeti almaya devam eder — süre bitişi current_period_end ile kontrol edilir.
     */
    public function grantsAccess(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::Grace, self::Cancelled], true);
    }

    /** Refund, erişimin ANINDA kesilmesi gereken tek durum. */
    public function revokesImmediately(): bool
    {
        return $this === self::Refunded;
    }
}
