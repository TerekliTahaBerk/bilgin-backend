<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resource;

use App\Modules\Identity\Infrastructure\Eloquent\Model\UserEnrollment;

/** Tek bir sınav kaydı — liste ve geçiş yanıtlarının ortak biçimi. */
final readonly class EnrollmentResource
{
    /** @return array<string, mixed> */
    public static function toArray(UserEnrollment $enrollment): array
    {
        $variant = $enrollment->examVariant;
        $exam = $variant->exam;

        return [
            'id' => $enrollment->id,
            'is_primary' => $enrollment->is_primary,
            'enrolled_at' => $enrollment->enrolled_at->toIso8601String(),
            'exam' => [
                'code' => $exam->code,
                'name' => $exam->name,
                'icon' => $exam->icon,
            ],
            'exam_variant' => [
                'code' => $variant->code,
                'name' => $variant->name,
                'field' => $variant->field_code->value,
                'description' => $variant->description,
            ],
        ];
    }
}
