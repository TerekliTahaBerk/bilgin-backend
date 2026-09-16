<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * auth:sanctum arkasındaki uçlarda kullanıcının var olduğu garantidir, ama
 * bu garanti middleware'de yaşar. Burada açıkça doğrulanması, middleware
 * yanlışlıkla kaldırılırsa sessiz bir null yerine 401 üretilmesini sağlar.
 */
abstract class ApiController extends Controller
{
    protected function userId(Request $request): int
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        return (int) $user->getAuthIdentifier();
    }
}
