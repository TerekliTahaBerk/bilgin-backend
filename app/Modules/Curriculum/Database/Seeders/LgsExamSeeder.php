<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Database\Seeders;

use App\Modules\Curriculum\Domain\Enum\FieldCode;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\Exam;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamSection;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamVariant;
use Illuminate\Database\Seeder;

/**
 * LGS — Liselere Geçiş Sınavı.
 *
 * YKS'den iki yapısal farkı var ve ikisi de modeli etkiliyor:
 *
 *   1. ALAN YOK. Öğrenci "Sayısal/Sözel" seçmiyor; herkes aynı dersleri
 *      çalışıyor. Tek bir `genel` varyant açılıyor.
 *   2. İKİ OTURUM var ama ikisi de aynı gün: sözel bölüm (Türkçe, İnkılap,
 *      Din, İngilizce) ve sayısal bölüm (Matematik, Fen). Bunlar YKS'nin
 *      TYT/AYT'si gibi ayrı sınavlar değil, tek sınavın iki yarısı.
 *
 * Hedef kitle 8. sınıf — YKS'den farklı bir yaş grubu. Kayıt akışındaki
 * sınıf adımı bunu zaten destekliyor.
 */
final class LgsExamSeeder extends Seeder
{
    private const SECTIONS = [
        ['code' => 'lgs_sozel', 'name' => 'Sözel Bölüm', 'duration_min' => 75, 'question_count' => 50],
        ['code' => 'lgs_sayisal', 'name' => 'Sayısal Bölüm', 'duration_min' => 80, 'question_count' => 40],
    ];

    public function run(): void
    {
        $exam = Exam::query()->updateOrCreate(
            ['code' => 'lgs'],
            ['name' => 'LGS', 'icon' => 'backpack', 'sort_order' => 2, 'is_active' => true],
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
                'code' => 'lgs_genel',
                'name' => 'LGS',
                'description' => 'Türkçe · Matematik · Fen · İnkılap · Din · İngilizce',
                'sort_order' => 1,
                'is_active' => true,
            ],
        );
    }
}
