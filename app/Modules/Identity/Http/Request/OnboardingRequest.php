<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Request;

use App\Modules\Curriculum\Domain\Enum\FieldCode;
use App\Modules\Identity\Domain\Enum\DailyGoal;
use App\Modules\Identity\Domain\Enum\Grade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OnboardingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'exam_code' => ['required', 'string', Rule::exists('exams', 'code')->where('is_active', true)],
            'field' => ['required', Rule::enum(FieldCode::class)],
            'grade' => ['nullable', Rule::enum(Grade::class)],
            'target_exam_year' => ['nullable', 'integer', 'min:2026', 'max:2040'],
            'target_exam_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'name' => ['nullable', 'string', 'min:2', 'max:20'],
            'avatar_key' => ['nullable', 'string', 'max:64'],
            'acquisition_source' => ['nullable', 'string', 'max:32'],
            'daily_goal_rounds' => ['nullable', Rule::enum(DailyGoal::class)],
            'reminder_enabled' => ['nullable', 'boolean'],
            'reminder_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.min' => 'İsim en az 2 karakter olmalı.',
            'name.max' => 'İsim en fazla 20 karakter olabilir.',
            'exam_code.exists' => 'Böyle bir sınav yok.',
        ];
    }
}
