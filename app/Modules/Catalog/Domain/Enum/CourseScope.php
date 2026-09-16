<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enum;

/**
 * Bir dersin müfredat kapsamı. Course hiçbir sınav varyantına ait değildir;
 * scope yalnızca "bu içerik hangi seviyeye yazıldı" bilgisidir. Aynı course
 * birden fazla varyant (ve ileride birden fazla sınav) tarafından referans alınır.
 */
enum CourseScope: string
{
    case Tyt = 'tyt';
    case Ayt = 'ayt';
    case Ydt = 'ydt';
    case Lgs = 'lgs';
    case Kpss = 'kpss';
    case Ales = 'ales';
    case Yds = 'yds';
}
