<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Application\UseCase;

use App\Modules\Curriculum\Domain\Contract\VariantCourseReader;
use App\Modules\Curriculum\Domain\ReadModel\VariantCourseRow;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamSection;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamVariant;

/**
 * "Öğren · ders seçimi" ekranı — tasarımdaki TYT / AYT sekmeleri.
 *
 * Tamamı veriden türer: hangi dersler, hangi sırada, hangi sekmede ve
 * hangileri premium — hepsi exam_variant_courses satırlarında yazılıdır.
 * Bu sınıfta "eğer Sayısal ise" türü tek bir koşul yoktur; olsaydı yeni
 * bir sınav eklemek kod değişikliği gerektirirdi.
 */
final readonly class GetLearnerCourses
{
    public function __construct(private VariantCourseReader $courses) {}

    public function __invoke(ExamVariant $variant, bool $hasPremium): LearnerCoursesView
    {
        $sections = ExamSection::query()
            ->where('exam_id', $variant->exam_id)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('id');

        /** @var array<int, SectionCoursesView> $grouped */
        $grouped = [];

        foreach ($this->courses->coursesFor($variant->id) as $row) {
            $section = $sections->get($row->examSectionId);

            if (! $section instanceof ExamSection) {
                continue;
            }

            $grouped[$row->examSectionId] ??= new SectionCoursesView($section, []);
            $grouped[$row->examSectionId] = $grouped[$row->examSectionId]
                ->with($this->buildCourseView($row, $hasPremium));
        }

        return new LearnerCoursesView($variant, array_values($grouped));
    }

    private function buildCourseView(VariantCourseRow $row, bool $hasPremium): LearnerCourseView
    {
        return new LearnerCourseView(
            row: $row,
            // Yayınlanmamış ders listeden düşmez, "Yakında" görünür:
            // öğrenciye yol haritasını anlatır ve beklentiyi yönetir.
            comingSoon: ! $row->published,
            locked: $row->requiresPremium() && ! $hasPremium,
            placeholderLabel: $row->placeholderLabel ?? ($row->published ? null : 'Yakında'),
        );
    }
}
