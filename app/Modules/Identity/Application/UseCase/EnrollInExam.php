<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCase;

use App\Modules\Curriculum\Domain\Enum\FieldCode;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\Exam;
use App\Modules\Curriculum\Infrastructure\Eloquent\Model\ExamVariant;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Modules\Identity\Infrastructure\Eloquent\Model\UserEnrollment;
use App\Shared\Clock\ClockInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * İkinci (üçüncü, ...) bir sınava kayıt olur ve onu birincil yapar.
 *
 * `user_enrollments` baştan çoğul tasarlanmış (`is_primary` + `user_id`
 * bazında tekillik yerine `user_id + exam_variant_id` bazında tekillik);
 * eksik olan tek şey bu kaydı AÇAN bir uçtu.
 *
 * Aynı sınavın başka bir alanına geçmek bu sınıfın işi DEĞİL — o
 * `ChangeExamField`. Aradaki fark önemli: alan değişiminde eski alan
 * kapanır, sınav eklemede ikisi birden yaşar ve öğrenci aralarında geçiş
 * yapar.
 */
final readonly class EnrollInExam
{
    public function __construct(private ClockInterface $clock) {}

    public function __invoke(int $userId, string $examCode, FieldCode $field): UserEnrollment
    {
        return DB::transaction(function () use ($userId, $examCode, $field): UserEnrollment {
            $user = User::query()->findOrFail($userId);

            $exam = Exam::query()
                ->where('code', $examCode)
                ->where('is_active', true)
                ->first()
                ?? throw new RuntimeException("'{$examCode}' sınavı yok ya da aktif değil.");

            $variant = ExamVariant::query()
                ->where('exam_id', $exam->id)
                ->where('field_code', $field->value)
                ->where('is_active', true)
                ->first()
                ?? throw new RuntimeException("Bu sınavda '{$field->value}' alanı yok.");

            // Hepsi ikincil; az sonra hedef kayıt birincile çekiliyor.
            // Sıra önemli: önce birincil atansaydı bu satır onu da ezerdi.
            $user->enrollments()->update(['is_primary' => false]);

            /*
             | `updateOrCreate`: aynı sınava daha önce kayıt olup başkasına
             | geçmiş bir öğrenci geri döndüğünde tekil dizin ihlali yerine
             | eski kaydı canlanmalı. Dönüş, bıraktığı yerden devam etmek.
             */
            $enrollment = $user->enrollments()->updateOrCreate(
                ['exam_variant_id' => $variant->id],
                ['is_primary' => true, 'enrolled_at' => $this->clock->now()],
            );

            $enrollment->load('examVariant.exam');

            return $enrollment;
        });
    }

    /** Var olan bir kaydı birincil yapar (sınav arası geçiş). */
    public function switchTo(int $userId, int $enrollmentId): UserEnrollment
    {
        return DB::transaction(function () use ($userId, $enrollmentId): UserEnrollment {
            $user = User::query()->findOrFail($userId);

            /*
             | Kayıt KULLANICI üzerinden aranıyor, kimliğiyle doğrudan
             | değil: başkasının kayıt kimliğini göndermek 404 almalı,
             | 403 bile değil — o kaydın varlığı sızdırılmamalı.
             */
            $target = $user->enrollments()->whereKey($enrollmentId)->firstOrFail();

            $user->enrollments()->update(['is_primary' => false]);
            $target->update(['is_primary' => true]);

            $target->load('examVariant.exam');

            return $target;
        });
    }
}
