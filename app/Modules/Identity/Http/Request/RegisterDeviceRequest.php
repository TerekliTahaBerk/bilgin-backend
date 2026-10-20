<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegisterDeviceRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'device_identifier' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in(['ios', 'android'])],
            // İzin reddedildiyse jeton gelmiyor; cihaz yine kaydediliyor.
            'push_token' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ];
    }
}
