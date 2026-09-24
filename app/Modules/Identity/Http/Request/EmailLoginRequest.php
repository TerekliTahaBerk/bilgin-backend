<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class EmailLoginRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:191'],
            // Girişte şifre KURALI YOK: kural koymak, eski şifrelerin
            // kuralı sağlamadığı durumda kullanıcıyı kendi hesabından
            // kilitler. Doğrulama yalnızca kayıtta.
            'password' => ['required', 'string'],
        ];
    }
}
