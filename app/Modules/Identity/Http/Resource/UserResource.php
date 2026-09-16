<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resource;

use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $profile = $this->profile;
        $variant = $this->primaryVariant();

        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'avatar_key' => $this->avatar_key,
            'is_guest' => $this->is_guest,
            'timezone' => $this->timezone,
            'enrollment' => $variant === null ? null : [
                'exam_variant' => [
                    'code' => $variant->code,
                    'name' => $variant->name,
                    'field' => $variant->field_code->value,
                ],
            ],
            'profile' => $profile === null ? null : [
                'grade' => $profile->grade?->value,
                'target_exam_year' => $profile->target_exam_year,
                'daily_goal_rounds' => $profile->daily_goal_rounds->value,
                'reminder_enabled' => $profile->reminder_enabled,
                'reminder_time' => $profile->reminder_time,
                'onboarding_completed' => $profile->onboarding_completed_at !== null,
            ],
        ];
    }
}
