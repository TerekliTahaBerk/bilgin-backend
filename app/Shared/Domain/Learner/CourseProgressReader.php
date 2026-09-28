<?php

declare(strict_types=1);

namespace App\Shared\Domain\Learner;

interface CourseProgressReader
{
    /**
     * Verilen derslerin ilerlemesi.
     *
     * Ders başına tek tek sormak yerine toplu: ders listesi ekranı yirmiye
     * yakın ders çiziyor ve her biri için ayrı sorgu açmak, en sık açılan
     * ekranı en pahalı ekran yapardı.
     *
     * Kaydı olmayan ders anahtarı DÖNMEZ; çağıran [CourseProgress::none()]
     * ile doldurur.
     *
     * @param  list<int>  $courseIds
     * @return array<int, CourseProgress> ders kimliği → ilerleme
     */
    public function forCourses(int $userId, array $courseIds): array;
}
