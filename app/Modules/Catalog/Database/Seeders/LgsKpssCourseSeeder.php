<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Domain\Enum\CourseScope;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Course;
use App\Modules\Catalog\Infrastructure\Eloquent\Model\Subject;
use App\Shared\Domain\Enum\PublishStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * LGS ve KPSS dersleri.
 *
 * YKS derslerinden ayrı bir dosyada: üçü farklı müfredatlar ve birini
 * güncellemek diğerini riske atmamalı.
 *
 * Hepsi TASLAK olarak açılıyor. Yayın bir VERİ kararıdır ve içerik
 * yerine oturmadan ders listesinde görünmeleri, öğrenciye boş bir yol
 * göstermek olurdu.
 */
final class LgsKpssCourseSeeder extends Seeder
{
    /** [kod, ders dalı, ad, kısa ad, kapsam, oturum, sınav ağırlığı] */
    private const COURSES = [
        /*
         | LGS — 8. sınıf.
         |
         | "Fen Bilimleri" tek ders olarak duruyor, fizik/kimya/biyoloji
         | diye bölünmüyor. Bu bir basitleştirme değil: 8. sınıfta ders
         | gerçekten böyle okutuluyor ve soru da böyle soruluyor.
        */
        ['lgs_turkce', 'turkce', 'LGS Türkçe', 'Türkçe', CourseScope::Lgs, 'lgs_sozel', 20],
        ['lgs_inkilap', 'tarih', 'LGS T.C. İnkılap Tarihi', 'İnkılap Tarihi', CourseScope::Lgs, 'lgs_sozel', 10],
        ['lgs_din', 'din_kulturu', 'LGS Din Kültürü', 'Din Kültürü', CourseScope::Lgs, 'lgs_sozel', 10],
        ['lgs_ingilizce', 'ingilizce', 'LGS İngilizce', 'İngilizce', CourseScope::Lgs, 'lgs_sozel', 10],
        ['lgs_matematik', 'matematik', 'LGS Matematik', 'Matematik', CourseScope::Lgs, 'lgs_sayisal', 20],
        ['lgs_fen', 'fen_bilimleri', 'LGS Fen Bilimleri', 'Fen Bilimleri', CourseScope::Lgs, 'lgs_sayisal', 20],

        /*
         | KPSS Lisans — yalnızca Genel Yetenek / Genel Kültür.
         |
         | Geometri ayrı bir ders olarak duruyor çünkü KPSS'de soru
         | dağılımı matematikten ayrı sayılıyor; aynı dalın (matematik)
         | altında ama kendi dersi.
        */
        ['kpss_turkce', 'turkce', 'KPSS Türkçe', 'Türkçe', CourseScope::Kpss, 'kpss_gy', 30],
        ['kpss_matematik', 'matematik', 'KPSS Matematik', 'Matematik', CourseScope::Kpss, 'kpss_gy', 23],
        ['kpss_geometri', 'matematik', 'KPSS Geometri', 'Geometri', CourseScope::Kpss, 'kpss_gy', 7],
        ['kpss_tarih', 'tarih', 'KPSS Tarih', 'Tarih', CourseScope::Kpss, 'kpss_gk', 27],
        ['kpss_cografya', 'cografya', 'KPSS Coğrafya', 'Coğrafya', CourseScope::Kpss, 'kpss_gk', 18],
        ['kpss_vatandaslik', 'vatandaslik', 'KPSS Vatandaşlık', 'Vatandaşlık', CourseScope::Kpss, 'kpss_gk', 9],
        ['kpss_guncel', 'guncel_bilgiler', 'KPSS Güncel Bilgiler', 'Güncel Bilgiler', CourseScope::Kpss, 'kpss_gk', 6],
    ];

    public function run(): void
    {
        $subjects = Subject::query()->pluck('id', 'code');
        $sections = DB::table('exam_sections')->pluck('id', 'code');
        $variants = DB::table('exam_variants')->pluck('id', 'code');

        // YKS dersleri 21 kayıt; sıralama onlardan sonra başlıyor.
        $order = 100;

        foreach (self::COURSES as [$code, $subjectCode, $name, $short, $scope, $section, $weight]) {
            $course = Course::query()->updateOrCreate(
                ['code' => $code],
                [
                    'subject_id' => $subjects[$subjectCode],
                    'name' => $name,
                    'short_name' => $short,
                    'scope' => $scope,
                    'default_section_id' => $sections[$section] ?? null,
                    // LGS 8. sınıf, KPSS mezun.
                    'grade_range' => $scope === CourseScope::Lgs ? ['8'] : ['mezun'],
                    'status' => PublishStatus::Draft,
                    'sort_order' => ++$order,
                ],
            );

            $variantCode = $scope === CourseScope::Lgs ? 'lgs_genel' : 'kpss_genel';

            if (! isset($variants[$variantCode], $sections[$section])) {
                continue;
            }

            DB::table('exam_variant_courses')->updateOrInsert(
                ['exam_variant_id' => $variants[$variantCode], 'course_id' => $course->id],
                [
                    'exam_section_id' => $sections[$section],
                    'sort_order' => $order,
                    'is_required' => true,
                    'access' => 'free',
                    'exam_weight' => $weight,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
