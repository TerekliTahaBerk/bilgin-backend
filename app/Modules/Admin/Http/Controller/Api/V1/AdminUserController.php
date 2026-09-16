<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controller\Api\V1;

use App\Modules\Admin\Application\UseCase\RecordAudit;
use App\Modules\Admin\Domain\Enum\AdminRole;
use App\Modules\Admin\Http\Controller\AdminController;
use App\Modules\Admin\Infrastructure\Eloquent\Model\AdminUser;
use App\Shared\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Yönetici hesabı yönetimi — yalnızca süper yönetici.
 *
 * İki kural var ve ikisi de kilitlenmeyi önlemek için:
 * kimse kendi rolünü düşüremez, kimse kendi hesabını kapatamaz. Aksi hâlde
 * tek süper yöneticinin bir yanlış tıkla sistemi kimsesiz bırakması mümkün
 * olurdu ve geri dönüş yolu yalnızca veritabanı olurdu.
 */
final class AdminUserController extends AdminController
{
    public function index(): JsonResponse
    {
        $admins = AdminUser::query()->orderBy('id')->get();

        return ApiResponse::data([
            'roles' => array_map(
                static fn (AdminRole $r): array => [
                    'value' => $r->value,
                    'label' => $r->label(),
                    'abilities' => [
                        'edit_content' => $r->canEditContent(),
                        'publish_content' => $r->canPublishContent(),
                        'edit_curriculum' => $r->canEditCurriculum(),
                        'view_users' => $r->canViewUsers(),
                    ],
                ],
                AdminRole::cases(),
            ),
            'admins' => $admins->map(fn (AdminUser $a): array => [
                'id' => $a->uuid,
                'name' => $a->name,
                'email' => $a->email,
                'role' => $a->role->value,
                'role_label' => $a->role->label(),
                'is_active' => $a->is_active,
                'last_login_at' => $a->last_login_at?->format(DATE_ATOM),
            ])->all(),
        ]);
    }

    public function store(Request $request, RecordAudit $audit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'unique:admin_users,email'],
            'password' => ['required', 'string', 'min:12'],
            'role' => ['required', Rule::enum(AdminRole::class)],
        ]);

        $admin = AdminUser::query()->create($data);

        $audit($this->adminId($request), 'admin_user.created', $admin, null, $request->ip());

        return ApiResponse::data([
            'id' => $admin->uuid,
            'email' => $admin->email,
            'role' => $admin->role->value,
        ], 201);
    }

    public function update(Request $request, string $admin, RecordAudit $audit): JsonResponse
    {
        $target = AdminUser::query()->where('uuid', $admin)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'role' => ['sometimes', Rule::enum(AdminRole::class)],
            'is_active' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'string', 'min:12'],
        ]);

        if ((int) $target->getAuthIdentifier() === $this->adminId($request)) {
            if (array_key_exists('role', $data) && $data['role'] !== $target->role->value) {
                return $this->lockoutError('Kendi rolünü değiştiremezsin.');
            }

            if (array_key_exists('is_active', $data) && $data['is_active'] === false) {
                return $this->lockoutError('Kendi hesabını kapatamazsın.');
            }
        }

        // Son aktif süper yöneticiyi kaybetmek, panele girişin tamamen
        // kapanması demek. Bu kontrol kendi hesabınla sınırlı değil:
        // iki süper yöneticiden biri diğerini de düşüremez.
        if ($this->wouldRemoveLastSuperAdmin($target, $data)) {
            return $this->lockoutError('Sistemde en az bir aktif süper yönetici kalmalı.');
        }

        $before = $target->getAttributes();
        $target->update($data);

        $audit($this->adminId($request), 'admin_user.updated', $target, $before, $request->ip());

        return ApiResponse::data([
            'id' => $target->uuid,
            'role' => $target->role->value,
            'is_active' => $target->is_active,
        ]);
    }

    /** @param  array<string, mixed>  $changes */
    private function wouldRemoveLastSuperAdmin(AdminUser $target, array $changes): bool
    {
        if ($target->role !== AdminRole::SuperAdmin || ! $target->is_active) {
            return false;
        }

        $losesRole = array_key_exists('role', $changes) && $changes['role'] !== AdminRole::SuperAdmin->value;
        $losesAccess = array_key_exists('is_active', $changes) && $changes['is_active'] === false;

        if (! $losesRole && ! $losesAccess) {
            return false;
        }

        $remaining = AdminUser::query()
            ->where('role', AdminRole::SuperAdmin)
            ->where('is_active', true)
            ->whereKeyNot($target->getKey())
            ->count();

        return $remaining === 0;
    }

    private function lockoutError(string $message): JsonResponse
    {
        return ApiResponse::error('ADMIN_LOCKOUT_PREVENTED', $message, 422);
    }
}
