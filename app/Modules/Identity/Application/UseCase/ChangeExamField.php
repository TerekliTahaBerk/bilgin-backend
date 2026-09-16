<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCase;

use App\Modules\Curriculum\Domain\Enum\FieldCode;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamVariant;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Modules\Identity\Infrastructure\Eloquent\Model\UserEnrollment;
use App\Shared\Clock\ClockInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Alan değiştirir (ör. Sayısal → Eşit Ağırlık).
 *
 * Öğrenciler alanını gerçekten değiştiriyor ve bu, ilerlemeyi silmenin
 * mazereti olamaz. Tüm ilerleme course_id'ye bağlı olduğu için burada
 * yapılan iş TEK SATIRLIK bir update'tir: TYT'nin tamamı ve ortak AYT
 * dersleri olduğu gibi kalır, yalnızca görünen ders listesi değişir.
 *
 * Bu sınıfın bu kadar kısa olması bir tesadüf değil; veri modelinin
 * doğru kurulmuş olmasının kanıtı.
 */
final readonly class ChangeExamField
{
    public function __construct(private ClockInterface $clock) {}

    public function __invoke(int $userId, FieldCode $newField): UserEnrollment
    {
        return DB::transaction(function () use ($userId, $newField): UserEnrollment {
            $user = User::query()->findOrFail($userId);

            $enrollment = $user->enrollments()->where('is_primary', true)->first()
                ?? throw new RuntimeException('Kullanıcının birincil kaydı yok — önce onboarding.');

            $currentVariant = $enrollment->examVariant;

            if ($currentVariant->field_code === $newField) {
                return $enrollment;
            }

            $target = ExamVariant::query()
                ->where('exam_id', $currentVariant->exam_id)
                ->where('field_code', $newField->value)
                ->where('is_active', true)
                ->first()
                ?? throw new RuntimeException("Bu sınavda '{$newField->value}' alanı yok.");

            $enrollment->update([
                'exam_variant_id' => $target->id,
                'field_changed_at' => $this->clock->now(),
            ]);

            $enrollment->load('examVariant');

            return $enrollment;
        });
    }
}
