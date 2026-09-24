<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:191'],
            // Sekiz karakter + harf + rakam. Daha katı kurallar (sembol
            // zorunluluğu, sık değiştirme) kullanıcıyı şifreyi bir yere
            // yazmaya itiyor; NIST de bunları artık önermiyor.
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'name' => ['nullable', 'string', 'min:2', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => 'E-posta gerekli.',
            'email.email' => 'Geçerli bir e-posta gir.',
            'password.required' => 'Şifre gerekli.',
            'name.min' => 'Ad en az 2 karakter olmalı.',
            'name.max' => 'Ad en fazla 20 karakter olabilir.',
        ];
    }
}
