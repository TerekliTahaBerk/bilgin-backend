<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class ForgotPasswordRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // `exists` KULLANILMIYOR: doğrulama hatası, e-postanın kayıtlı
            // olup olmadığını ele verirdi. Kayıtlı değilse de aynı yanıt.
            'email' => ['required', 'email:rfc', 'max:191'],
        ];
    }
}
