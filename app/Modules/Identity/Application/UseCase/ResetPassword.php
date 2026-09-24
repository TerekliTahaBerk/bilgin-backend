<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCase;

use App\Modules\Identity\Domain\Exception\EmailAuthException;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Şifreyi sıfırlar.
 *
 * Token tek kullanımlık ve süreli; Laravel'in broker'ı ikisini de yönetiyor
 * (tablo `password_reset_tokens`, token veritabanında hash'li duruyor).
 *
 * Sıfırlama sonrası TÜM OTURUMLAR KAPANIYOR. Şifre sıfırlamanın en yaygın
 * sebebi hesabın ele geçirilmiş olmasıdır; eski token'ları geçerli bırakmak,
 * saldırganı içeride tutmak demektir.
 */
final readonly class ResetPassword
{
    public function __invoke(string $email, string $token, string $password): User
    {
        $status = Password::broker()->reset(
            [
                'email' => mb_strtolower(trim($email)),
                'password' => $password,
                'password_confirmation' => $password,
                'token' => $token,
            ],
            function (User $user) use ($password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                Event::dispatch(new PasswordResetEvent($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw EmailAuthException::resetTokenInvalid();
        }

        $user = User::query()->where('email', mb_strtolower(trim($email)))->firstOrFail();

        return $user->fresh(['profile', 'enrollments.examVariant']) ?? $user;
    }
}
