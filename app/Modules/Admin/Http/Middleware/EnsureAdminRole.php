<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Middleware;

use App\Modules\Admin\Domain\Enum\AdminRole;
use App\Modules\Admin\Infrastructure\Eloquent\Model\AdminUser;
use App\Shared\Http\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Yetkiyi rota tanımında zorunlu kılar: `->middleware('admin.can:publish')`.
 *
 * Kontrolü controller içine yazmak, yeni bir uç eklerken unutulmaya açıktır;
 * rotada durunca yetkisiz uç eklemek kasıt gerektirir.
 */
final class EnsureAdminRole
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $admin = $request->user('admin');

        if (! $admin instanceof AdminUser || ! $admin->is_active) {
            return ApiResponse::error('FORBIDDEN', 'Bu işlem için yetkin yok.', 403);
        }

        if (! $this->allows($admin->role, $ability)) {
            return ApiResponse::error(
                'FORBIDDEN',
                "Bu işlem için yetkin yok ({$admin->role->label()}).",
                403,
            );
        }

        return $next($request);
    }

    private function allows(AdminRole $role, string $ability): bool
    {
        return match ($ability) {
            'edit' => $role->canEditContent(),
            'publish' => $role->canPublishContent(),
            'curriculum' => $role->canEditCurriculum(),
            'users' => $role->canViewUsers(),
            default => false,
        };
    }
}
