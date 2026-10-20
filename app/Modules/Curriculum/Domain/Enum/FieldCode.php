<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Domain\Enum;

/**
 * YKS alan kodu. Onboarding'in "Hangi bölümdesin?" adımı bunu üretir ve
 * sınav koduyla birlikte tek bir ExamVariant'a çözülür (yks + say → yks_say).
 *
 * Undecided ("Henüz bilmiyorum") gerçek bir varyanttır, null değildir:
 * yalnızca TYT derslerine eşlenmiş bir varyant. Böylece kod hiçbir yerde
 * "alanı yoksa şunu yap" istisnası taşımaz.
 */
enum FieldCode: string
{
    case Sayisal = 'say';
    case EsitAgirlik = 'ea';
    case Sozel = 'soz';
    case Dil = 'dil';
    case Undecided = 'undecided';

    /*
     | Alan ayrımı OLMAYAN sınavlar için (LGS, KPSS Genel Yetenek).
     |
     | Bu sınavlarda öğrenci "Sayısal/Sözel" seçmiyor; herkes aynı
     | dersleri çalışıyor. Varyant modelini zorlamak yerine tek bir
     | "genel" varyant açılıyor — böylece kayıt akışı, ders listesi ve
     | lig kodunun hiçbirinde "alanı yoksa" istisnası oluşmuyor.
    */
    case Genel = 'genel';

    public function label(): string
    {
        return match ($this) {
            self::Sayisal => 'Sayısal',
            self::EsitAgirlik => 'Eşit Ağırlık',
            self::Sozel => 'Sözel',
            self::Dil => 'Dil',
            self::Undecided => 'Henüz bilmiyorum',
            self::Genel => 'Genel',
        };
    }
}
