<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Database\Seeders;

use App\Modules\Curriculum\Domain\Enum\FieldCode;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\Exam;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamSection;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamVariant;
use Illuminate\Database\Seeder;

/**
 * KPSS Lisans — Genel Yetenek / Genel Kültür.
 *
 * Diğer iki sınavdan FARKLI BİR KİTLE: mezunlar. Lise öğrencisine göre
 * kurulmuş varsayımların bir kısmı burada geçerli değil — sınıf seviyesi
 * sorusunun karşılığı yok, hedef sınav yılı daha kısa vadeli ve çalışma
 * düzeni gün içinde daha dağınık.
 *
 * Bu paket yalnızca GY-GK bölümünü kapsıyor; alan sınavları (Eğitim
 * Bilimleri, ÖABT, A Grubu) yok. Tek varyant açılması bu yüzden:
 * kapsamadığımız bir bölümü varmış gibi göstermek, hazırlanan kişiyi
 * yanıltır.
 */
final class KpssExamSeeder extends Seeder
{
    private const SECTIONS = [
        ['code' => 'kpss_gy', 'name' => 'Genel Yetenek', 'duration_min' => 65, 'question_count' => 60],
        ['code' => 'kpss_gk', 'name' => 'Genel Kültür', 'duration_min' => 65, 'question_count' => 60],
    ];

    public function run(): void
    {
        $exam = Exam::query()->updateOrCreate(
            ['code' => 'kpss'],
            ['name' => 'KPSS', 'icon' => 'briefcase', 'sort_order' => 3, 'is_active' => true],
        );

        foreach (self::SECTIONS as $index => $section) {
            ExamSection::query()->updateOrCreate(
                ['exam_id' => $exam->id, 'code' => $section['code']],
                [...$section, 'sort_order' => $index + 1],
            );
        }

        ExamVariant::query()->updateOrCreate(
            ['exam_id' => $exam->id, 'field_code' => FieldCode::Genel->value],
            [
                'code' => 'kpss_genel',
                'name' => 'KPSS · Genel Yetenek–Genel Kültür',
                'description' => 'Türkçe · Matematik · Tarih · Coğrafya · Vatandaşlık',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );
    }
}
