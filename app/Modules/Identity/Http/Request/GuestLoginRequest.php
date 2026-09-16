<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class GuestLoginRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'device_identifier' => ['required', 'string', 'max:191'],
            'platform' => ['required', 'string', 'in:ios,android,web'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ];
    }
}
