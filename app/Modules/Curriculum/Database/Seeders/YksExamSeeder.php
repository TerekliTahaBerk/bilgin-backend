<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Database\Seeders;

use App\Modules\Curriculum\Domain\Enum\FieldCode;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\Exam;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamSection;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamVariant;
use Illuminate\Database\Seeder;

/**
 * YKS'nin sınav yapısı: oturumlar (TYT/AYT/YDT) ve alan varyantları.
 *
 * Varyantlar onboarding'in 1. ve 2. adımının çıktısıdır. "Henüz bilmiyorum"
 * da gerçek bir varyanttır (yalnızca TYT'ye eşlenir) — böylece kod hiçbir
 * yerde "alanı yoksa" istisnası taşımaz.
 */
final class YksExamSeeder extends Seeder
{
    private const SECTIONS = [
        ['code' => 'tyt', 'name' => 'TYT', 'duration_min' => 165, 'question_count' => 120],
        ['code' => 'ayt', 'name' => 'AYT', 'duration_min' => 180, 'question_count' => 80],
        ['code' => 'ydt', 'name' => 'YDT', 'duration_min' => 120, 'question_count' => 80],
    ];

    private const VARIANTS = [
        [FieldCode::Sayisal, 'YKS · Sayısal', 'Matematik · Fizik · Kimya · Biyoloji'],
        [FieldCode::EsitAgirlik, 'YKS · Eşit Ağırlık', 'Matematik · Edebiyat · Tarih · Coğrafya'],
        [FieldCode::Sozel, 'YKS · Sözel', 'Edebiyat · Tarih · Coğrafya · Felsefe'],
        [FieldCode::Dil, 'YKS · Dil', 'İngilizce ağırlıklı'],
        [FieldCode::Undecided, 'YKS · Henüz bilmiyorum', 'TYT dersleriyle başlarız'],
    ];

    public function run(): void
    {
        $exam = Exam::query()->updateOrCreate(
            ['code' => 'yks'],
            ['name' => 'YKS', 'icon' => 'graduation-cap', 'sort_order' => 1, 'is_active' => true],
        );

        foreach (self::SECTIONS as $index => $section) {
            ExamSection::query()->updateOrCreate(
                ['exam_id' => $exam->id, 'code' => $section['code']],
                [...$section, 'sort_order' => $index + 1],
            );
        }

        foreach (self::VARIANTS as $index => [$field, $name, $description]) {
            ExamVariant::query()->updateOrCreate(
                ['exam_id' => $exam->id, 'field_code' => $field->value],
                [
                    'code' => "yks_{$field->value}",
                    'name' => $name,
                    'description' => $description,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
