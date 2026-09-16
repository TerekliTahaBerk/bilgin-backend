<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Database\Seeders;

use App\Modules\Curriculum\Domain\Enum\FieldCode;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * SİSTEMİN CANLI SPESİFİKASYONU — hangi alan hangi dersleri görür.
 *
 * TYT dersleri her alanda ortaktır ama SIRASI alana göre değişir; tasarımdaki
 * "Derslerini buna göre sıralayacağım" sözü burada karşılanır. Aynı course
 * kaydı beş varyantta da aynı satırı işaret eder — içerik kopyalanmaz.
 *
 * Bu dosyayı değiştirmek müfredatı değiştirmenin geliştirici yoludur; içerik
 * ekibinin yolu admin panelidir (PUT /admin/v1/exam-variants/{id}/courses).
 */
final class YksCurriculumMapSeeder extends Seeder
{
    /** Soru adetleri: TYT 120 soruluk oturumun ders dağılımı. */
    private const TYT_WEIGHTS = [
        'tyt_turkce' => 40, 'tyt_matematik' => 40, 'tyt_tarih' => 5, 'tyt_cografya' => 5,
        'tyt_felsefe' => 5, 'tyt_din' => 5, 'tyt_fizik' => 7, 'tyt_kimya' => 7, 'tyt_biyoloji' => 6,
    ];

    /** Alana göre TYT ders sırası — öğrencinin en çok ihtiyacı olan üstte. */
    private const TYT_ORDER = [
        'say' => ['tyt_matematik', 'tyt_fizik', 'tyt_kimya', 'tyt_biyoloji', 'tyt_turkce', 'tyt_tarih', 'tyt_cografya', 'tyt_felsefe', 'tyt_din'],
        'ea' => ['tyt_matematik', 'tyt_turkce', 'tyt_tarih', 'tyt_cografya', 'tyt_felsefe', 'tyt_din', 'tyt_fizik', 'tyt_kimya', 'tyt_biyoloji'],
        'soz' => ['tyt_turkce', 'tyt_tarih', 'tyt_cografya', 'tyt_felsefe', 'tyt_din', 'tyt_matematik', 'tyt_fizik', 'tyt_kimya', 'tyt_biyoloji'],
        'dil' => ['tyt_turkce', 'tyt_matematik', 'tyt_tarih', 'tyt_cografya', 'tyt_felsefe', 'tyt_din', 'tyt_fizik', 'tyt_kimya', 'tyt_biyoloji'],
        'undecided' => ['tyt_turkce', 'tyt_matematik', 'tyt_tarih', 'tyt_cografya', 'tyt_fizik', 'tyt_kimya', 'tyt_biyoloji', 'tyt_felsefe', 'tyt_din'],
    ];

    /** Alana göre ikinci oturum dersleri: [ders => o alandaki soru adedi]. */
    private const SECOND_SESSION = [
        'say' => ['ayt_matematik' => 40, 'ayt_fizik' => 14, 'ayt_kimya' => 13, 'ayt_biyoloji' => 13],
        'ea' => ['ayt_matematik' => 40, 'ayt_edebiyat' => 24, 'ayt_tarih_1' => 10, 'ayt_cografya_1' => 6],
        'soz' => [
            'ayt_edebiyat' => 24, 'ayt_tarih_1' => 10, 'ayt_cografya_1' => 6,
            'ayt_tarih_2' => 11, 'ayt_cografya_2' => 11, 'ayt_felsefe_grubu' => 12, 'ayt_din' => 6,
        ],
        'dil' => ['ydt_ingilizce' => 80],
        'undecided' => [],   // yalnızca TYT
    ];

    /**
     * Premium'la açılan dersler — tasarımdaki "Din Kültürü · Premium ile açılır".
     * Bu bir KOD kararı değil, eşleme satırındaki bir kolondur.
     */
    private const PREMIUM_COURSES = ['tyt_din', 'ayt_din'];

    public function run(): void
    {
        $courses = DB::table('courses')->pluck('id', 'code');
        $sections = DB::table('exam_sections')->pluck('id', 'code');
        $variants = ExamVariant::query()->get()->keyBy(fn (ExamVariant $v): string => $v->field_code->value);

        foreach (FieldCode::cases() as $field) {
            $variant = $variants[$field->value] ?? null;

            if ($variant === null) {
                continue;
            }

            $order = 1;

            foreach (self::TYT_ORDER[$field->value] as $code) {
                $this->map($variant->id, $courses[$code], $sections['tyt'], $order++, self::TYT_WEIGHTS[$code], $code);
            }

            foreach (self::SECOND_SESSION[$field->value] as $code => $weight) {
                $sectionCode = str_starts_with($code, 'ydt_') ? 'ydt' : 'ayt';
                $this->map($variant->id, $courses[$code], $sections[$sectionCode], $order++, $weight, $code);
            }
        }
    }

    private function map(int $variantId, int $courseId, int $sectionId, int $order, int $weight, string $code): void
    {
        DB::table('exam_variant_courses')->updateOrInsert(
            ['exam_variant_id' => $variantId, 'course_id' => $courseId],
            [
                'exam_section_id' => $sectionId,
                'sort_order' => $order,
                'is_required' => true,
                'access' => in_array($code, self::PREMIUM_COURSES, true) ? 'premium' : 'free',
                'exam_weight' => $weight,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }
}
