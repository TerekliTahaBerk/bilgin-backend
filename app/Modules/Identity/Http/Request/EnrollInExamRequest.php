<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Request;

use App\Modules\Curriculum\Domain\Enum\FieldCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EnrollInExamRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /*
             | Varlık BURADA doğrulanıyor, use case'te değil.
             |
             | Use case yine de kontrol ediyor (aktiflik ve alan eşleşmesi
             | için) ama oradan çıkan `RuntimeException` istemciye 500
             | olarak ulaşıyordu. Yanlış sınav kodu bir sunucu arızası
             | değil, doğrulama hatasıdır ve 422 dönmelidir.
             */
            'exam_code' => ['required', 'string', 'max:32', Rule::exists('exams', 'code')],
            'field' => ['required', Rule::enum(FieldCode::class)],
        ];
    }
}
