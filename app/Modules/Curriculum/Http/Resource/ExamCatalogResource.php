<?php

declare(strict_types=1);

namespace App\Modules\Curriculum\Http\Resource;

use App\Modules\Curriculum\Infrastructure\Eloquent\Model\Exam;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamVariant;
use Illuminate\Database\Eloquent\Collection;

/** Kayıt akışının sınav ve alan seçenekleri. */
final readonly class ExamCatalogResource
{
    /**
     * @param  Collection<int, Exam>  $exams
     * @return array<string, mixed>
     */
    public static function toArray(Collection $exams): array
    {
        return ['exams' => $exams->map(self::exam(...))->all()];
    }

    /** @return array<string, mixed> */
    private static function exam(Exam $exam): array
    {
        return array_filter([
            'code' => $exam->code,
            'name' => $exam->name,
            'icon' => $exam->icon,
            'variants' => $exam->variants->map(self::variant(...))->all(),
        ], static fn ($v): bool => $v !== null);
    }

    /** @return array<string, mixed> */
    private static function variant(ExamVariant $variant): array
    {
        return array_filter([
            'code' => $variant->code,
            'name' => $variant->name,
            // Alan kodu (`say`, `ea`, ...) onboarding isteğinde geri
            // gönderiliyor; istemci kendi listesini tutmasın diye burada.
            'field' => $variant->field_code->value,
            'description' => $variant->description,
        ], static fn ($v): bool => $v !== null);
    }
}
