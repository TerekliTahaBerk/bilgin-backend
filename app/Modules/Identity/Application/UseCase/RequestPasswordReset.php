<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCase;

use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Modules\Identity\Notification\ResetPasswordNotification;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * Şifre sıfırlama bağlantısı ister.
 *
 * HER ZAMAN BAŞARILI DÖNER. E-posta kayıtlı değilse de aynı yanıt gider:
 * "bu e-posta bizde yok" demek, hangi adreslerin sistemde olduğunu sızdıran
 * bir sorgulama aracı yaratır. Kullanıcıya söylenen şey hep aynı — "kayıtlıysa
 * gönderdik".
 *
 * Sosyal girişle açılmış (şifresiz) hesaplara da bağlantı gönderilmiyor;
 * orada sıfırlanacak bir şifre yok.
 */
final readonly class RequestPasswordReset
{
    public function __invoke(string $email): void
    {
        $email = mb_strtolower(trim($email));

        $user = User::query()
            ->where('email', $email)
            ->whereNotNull('password')
            ->first();

        if ($user === null) {
            return;
        }

        try {
            $token = Password::broker()->createToken($user);
            $user->notify(new ResetPasswordNotification($token));
        } catch (Throwable $e) {
            // Gönderilemese bile isteğe aynı yanıt dönüyor; aksi hâlde
            // posta kesintisi, hesabın varlığını ele veren bir sinyale
            // dönüşürdü.
            report($e);
        }
    }
}
