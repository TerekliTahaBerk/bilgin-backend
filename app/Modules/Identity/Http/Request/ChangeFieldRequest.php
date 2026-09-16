<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Request;

use App\Modules\Curriculum\Domain\Enum\FieldCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeFieldRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['field' => ['required', Rule::enum(FieldCode::class)]];
    }
}
