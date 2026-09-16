<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Validation;

use App\Modules\Catalog\Domain\Enum\ExerciseType;

/**
 * Bir egzersiz tipinin içerik ve cevap anahtarı şemasını doğrular.
 *
 * Her doğrulayıcı, aynı tipin GRADER'ının okuduğu alanları kontrol eder.
 * İkisi arasındaki uyumsuzluk tam olarak önlemeye çalıştığımız hata sınıfı:
 * paneldeki bir yazım hatası, öğrenci o soruya geldiğinde çalışma anında
 * patlardı — üstelik turun ortasında.
 *
 * Yeni tip eklemek: enum'a case, bir grader, bir doğrulayıcı. Üçü birlikte.
 */
interface ExerciseContentValidator
{
    public function type(): ExerciseType;

    /**
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $answerKey
     */
    public function validate(array $content, array $answerKey): ContentValidationResult;
}
