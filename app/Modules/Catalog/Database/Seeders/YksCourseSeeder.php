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
 * YKS'nin 21 dersi.
 *
 * Bu ders kayıtlarının HİÇBİRİ bir alana (Sayısal/EA/…) ait değildir.
 * "AYT Matematik hem Sayısal'da hem EA'da" ifadesi burada değil,
 * exam_variant_courses eşlemesinde iki satır olarak durur — böylece içerik
 * tek kayıttır, kopyalanmaz.
 */
final class YksCourseSeeder extends Seeder
{
    /** [kod, subject, ad, kısa ad, kapsam, oturum] */
    private const COURSES = [
        // TYT — 9 ders, tüm alanlar için ortak
        ['tyt_turkce', 'turkce', 'TYT Türkçe', 'Türkçe', CourseScope::Tyt, 'tyt'],
        ['tyt_matematik', 'matematik', 'TYT Temel Matematik', 'Matematik', CourseScope::Tyt, 'tyt'],
        ['tyt_tarih', 'tarih', 'TYT Tarih', 'Tarih', CourseScope::Tyt, 'tyt'],
        ['tyt_cografya', 'cografya', 'TYT Coğrafya', 'Coğrafya', CourseScope::Tyt, 'tyt'],
        ['tyt_felsefe', 'felsefe', 'TYT Felsefe', 'Felsefe', CourseScope::Tyt, 'tyt'],
        ['tyt_din', 'din_kulturu', 'TYT Din Kültürü', 'Din Kültürü', CourseScope::Tyt, 'tyt'],
        ['tyt_fizik', 'fizik', 'TYT Fizik', 'Fizik', CourseScope::Tyt, 'tyt'],
        ['tyt_kimya', 'kimya', 'TYT Kimya', 'Kimya', CourseScope::Tyt, 'tyt'],
        ['tyt_biyoloji', 'biyoloji', 'TYT Biyoloji', 'Biyoloji', CourseScope::Tyt, 'tyt'],

        // AYT — 11 ders, alana göre eşlenir
        ['ayt_matematik', 'matematik', 'AYT Matematik', 'Matematik', CourseScope::Ayt, 'ayt'],
        ['ayt_fizik', 'fizik', 'AYT Fizik', 'Fizik', CourseScope::Ayt, 'ayt'],
        ['ayt_kimya', 'kimya', 'AYT Kimya', 'Kimya', CourseScope::Ayt, 'ayt'],
        ['ayt_biyoloji', 'biyoloji', 'AYT Biyoloji', 'Biyoloji', CourseScope::Ayt, 'ayt'],
        ['ayt_edebiyat', 'edebiyat', 'AYT Türk Dili ve Edebiyatı', 'Edebiyat', CourseScope::Ayt, 'ayt'],
        ['ayt_tarih_1', 'tarih', 'AYT Tarih-1', 'Tarih-1', CourseScope::Ayt, 'ayt'],
        ['ayt_cografya_1', 'cografya', 'AYT Coğrafya-1', 'Coğrafya-1', CourseScope::Ayt, 'ayt'],
        ['ayt_tarih_2', 'tarih', 'AYT Tarih-2', 'Tarih-2', CourseScope::Ayt, 'ayt'],
        ['ayt_cografya_2', 'cografya', 'AYT Coğrafya-2', 'Coğrafya-2', CourseScope::Ayt, 'ayt'],
        ['ayt_felsefe_grubu', 'felsefe', 'AYT Felsefe Grubu', 'Felsefe Grubu', CourseScope::Ayt, 'ayt'],
        ['ayt_din', 'din_kulturu', 'AYT Din Kültürü', 'Din Kültürü', CourseScope::Ayt, 'ayt'],

        // YDT — Dil alanı
        ['ydt_ingilizce', 'ingilizce', 'YDT İngilizce', 'İngilizce', CourseScope::Ydt, 'ydt'],
    ];

    /**
     * Lansmanda yayınlanan dersler. Gerisi hazır oldukça açılır — yayın bir
     * VERİ kararıdır, deploy değil. AYT Matematik lansmanda: AYT yolunun
     * (alan filtresi, sekme, blueprint, paywall) gerçek kullanıcıyla
     * doğrulanması 11 AYT dersini yazdıktan sonra keşfetmekten ucuzdur.
     */
    private const PUBLISHED_AT_LAUNCH = [
        'tyt_turkce', 'tyt_matematik', 'tyt_tarih', 'tyt_cografya', 'ayt_matematik',
    ];

    public function run(): void
    {
        $subjects = Subject::query()->pluck('id', 'code');
        $sections = DB::table('exam_sections')->pluck('id', 'code');

        foreach (self::COURSES as $index => [$code, $subjectCode, $name, $shortName, $scope, $section]) {
            $published = in_array($code, self::PUBLISHED_AT_LAUNCH, true);

            Course::query()->updateOrCreate(
                ['code' => $code],
                [
                    'subject_id' => $subjects[$subjectCode],
                    'name' => $name,
                    'short_name' => $shortName,
                    'scope' => $scope,
                    'default_section_id' => $sections[$section] ?? null,
                    'grade_range' => $scope === CourseScope::Tyt ? ['9', '10', '11', '12', 'mezun'] : ['11', '12', 'mezun'],
                    'status' => $published ? PublishStatus::Published : PublishStatus::Draft,
                    'published_at' => $published ? now() : null,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
