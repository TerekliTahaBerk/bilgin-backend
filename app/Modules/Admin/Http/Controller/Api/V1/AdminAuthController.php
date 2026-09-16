<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controller\Api\V1;

use App\Modules\Admin\Http\Controller\AdminController;
use App\Modules\Admin\Infrastructure\Eloquent\Model\AdminUser;
use App\Shared\Clock\ClockInterface;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AdminAuthController extends AdminController
{
    public function login(Request $request, ClockInterface $clock): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = AdminUser::query()->where('email', $credentials['email'])->first();

        // Kullanıcı yok ile şifre yanlış AYNI hatayı döner: hangi e-postanın
        // kayıtlı olduğu bilgisi sızdırılmaz.
        if ($admin === null || ! Hash::check($credentials['password'], $admin->password) || ! $admin->is_active) {
            throw ValidationException::withMessages(['email' => 'E-posta veya şifre hatalı.']);
        }

        $admin->update(['last_login_at' => $clock->now()]);

        return ApiResponse::data([
            'token' => $admin->createToken('admin-panel', ['admin'])->plainTextToken,
            'admin' => [
                'id' => $admin->uuid,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role->value,
                'role_label' => $admin->role->label(),
                'abilities' => [
                    'edit_content' => $admin->role->canEditContent(),
                    'publish_content' => $admin->role->canPublishContent(),
                    'edit_curriculum' => $admin->role->canEditCurriculum(),
                    'view_users' => $admin->role->canViewUsers(),
                ],
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $admin = $this->admin($request);

        return ApiResponse::data([
            'id' => $admin->uuid,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role->value,
            'role_label' => $admin->role->label(),
        ]);
    }
}
