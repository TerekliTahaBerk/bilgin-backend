<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Database\Seeders;

use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamBlueprint;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamBlueprintItem;
use App\Shared\Domain\Enum\PublishStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Deneme kompozisyonları. "Sınav Provası" ekranı ve net/puan tahmini
 * tamamen buradan beslenir.
 *
 * LGS eklendiğinde tek fark scoring_rule'dur (3 yanlış = 1 doğru →
 * penalty_ratio 0.333); motor aynı kalır.
 */
final class YksBlueprintSeeder extends Seeder
{
    private const BLUEPRINTS = [
        [
            'code' => 'tyt_genel_deneme',
            'name' => 'TYT Genel Deneme',
            'section' => 'tyt',
            'variant' => null,
            'duration_min' => 165,
            'items' => [
                'tyt_turkce' => 40, 'tyt_matematik' => 40,
                'tyt_tarih' => 5, 'tyt_cografya' => 5, 'tyt_felsefe' => 5, 'tyt_din' => 5,
                'tyt_fizik' => 7, 'tyt_kimya' => 7, 'tyt_biyoloji' => 6,
            ],
        ],
        [
            'code' => 'ayt_say_deneme',
            'name' => 'AYT Sayısal Deneme',
            'section' => 'ayt',
            'variant' => 'yks_say',
            'duration_min' => 180,
            'items' => [
                'ayt_matematik' => 40, 'ayt_fizik' => 14, 'ayt_kimya' => 13, 'ayt_biyoloji' => 13,
            ],
        ],
    ];

    /** Gerçek sınav zorluk dağılımına yakın varsayılan. */
    private const DIFFICULTY_DISTRIBUTION = [
        '1' => 0.20, '2' => 0.30, '3' => 0.30, '4' => 0.15, '5' => 0.05,
    ];

    public function run(): void
    {
        $examId = DB::table('exams')->where('code', 'yks')->value('id');
        $sections = DB::table('exam_sections')->pluck('id', 'code');
        $variants = DB::table('exam_variants')->pluck('id', 'code');
        $courses = DB::table('courses')->pluck('id', 'code');

        foreach (self::BLUEPRINTS as $spec) {
            $blueprint = ExamBlueprint::query()->updateOrCreate(
                ['code' => $spec['code']],
                [
                    'exam_id' => $examId,
                    'exam_section_id' => $sections[$spec['section']],
                    'exam_variant_id' => $spec['variant'] !== null ? $variants[$spec['variant']] : null,
                    'name' => $spec['name'],
                    'duration_min' => $spec['duration_min'],
                    // YKS: yanlış 1/4 götürür. Ham puan tabanı 100.
                    'scoring_rule' => ['penalty_ratio' => 0.25, 'base_score' => 100],
                    'status' => PublishStatus::Published,
                ],
            );

            $order = 1;

            foreach ($spec['items'] as $courseCode => $count) {
                ExamBlueprintItem::query()->updateOrCreate(
                    ['exam_blueprint_id' => $blueprint->id, 'course_id' => $courses[$courseCode]],
                    [
                        'question_count' => $count,
                        'difficulty_distribution' => self::DIFFICULTY_DISTRIBUTION,
                        'sort_order' => $order++,
                    ],
                );
            }
        }
    }
}
