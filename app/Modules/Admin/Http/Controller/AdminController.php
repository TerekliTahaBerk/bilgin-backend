<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controller;

use App\Modules\Admin\Infrastructure\Eloquent\Model\AdminUser;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * auth:admin arkasındaki uçlarda yöneticinin var olduğu garantidir, ama bu
 * garanti middleware'de yaşar. Burada açıkça doğrulanması, middleware
 * yanlışlıkla kaldırılırsa sessiz bir null yerine 401 üretilmesini sağlar.
 */
abstract class AdminController extends Controller
{
    protected function admin(Request $request): AdminUser
    {
        $admin = $request->user('admin');

        if (! $admin instanceof AdminUser) {
            throw new AuthenticationException;
        }

        return $admin;
    }

    protected function adminId(Request $request): int
    {
        return (int) $this->admin($request)->getAuthIdentifier();
    }
}
