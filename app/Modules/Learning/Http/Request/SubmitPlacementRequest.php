<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitPlacementRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /*
             | `answers` bir HARİTA: exercise_id => cevap gövdesi.
             |
             | Cevabın İÇ yapısı burada doğrulanmıyor çünkü tipe göre
             | değişiyor ({"option_id":"b"} · {"blanks":[...]} · {"value":42})
             | ve her tipin kuralını buraya yazmak, grader'larda zaten var
             | olan bilgiyi ikinci kez — ve zamanla tutarsız biçimde —
             | tanımlamak olurdu. Tanımadığı anahtarı grader yanlış sayar.
             */
            'answers' => ['present', 'array', 'max:50'],
            'answers.*' => ['array'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['answers.present' => 'Cevap listesi eksik.'];
    }
}
