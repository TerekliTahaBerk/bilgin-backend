<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Request;

use App\Modules\Identity\Domain\Enum\SocialProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SocialLoginRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(SocialProvider::class)],
            'identity_token' => ['required', 'string', 'max:4096'],
            // Çakışma ekranında kullanıcı "mevcut hesabımla devam et" derse
            // istemci bunu true gönderir. Varsayılan false: hiçbir ilerleme
            // sorulmadan silinmez.
            'discard_guest_progress' => ['nullable', 'boolean'],
        ];
    }
}
