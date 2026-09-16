<?php

declare(strict_types=1);

namespace App\Modules\Learning\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

final class StartSessionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'node_id' => ['required', 'integer', 'exists:unit_nodes,id'],
        ];
    }
}
