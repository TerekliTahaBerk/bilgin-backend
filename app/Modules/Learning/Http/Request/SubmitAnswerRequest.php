<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitAnswerRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'exercise_id' => ['required', 'integer'],
            // Şekli egzersiz tipine göre değişir; doğrulaması grader'ın işi.
            // Burada tip zorlamak, yeni egzersiz tipi eklemeyi bu dosyaya
            // dokunmak zorunda bırakırdı.
            'answer' => ['required', 'array'],
            'elapsed_ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
        ];
    }
}
