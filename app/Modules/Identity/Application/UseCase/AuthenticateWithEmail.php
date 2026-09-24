<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCase;

use App\Modules\Identity\Domain\Exception\EmailAuthException;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Shared\Clock\ClockInterface;
use Illuminate\Support\Facades\Hash;

/**
 * E-posta ve şifreyle giriş.
 */
final readonly class AuthenticateWithEmail
{
    public function __construct(private ClockInterface $clock) {}

    public function __invoke(string $email, string $password): User
    {
        $user = User::query()
            ->where('email', mb_strtolower(trim($email)))
            ->whereNotNull('password')
            ->first();

        /*
         | Kullanıcı yok ile şifre yanlış AYNI hatayı döner ve şifre yokken
         | de hash karşılaştırması YAPILIR. İkisi de bilinçli:
         |
         | - Farklı hata, hangi e-postanın kayıtlı olduğunu sızdırır.
         | - Erken dönmek, yanıt SÜRESİNİ farklılaştırır; saldırgan hesabın
         |   varlığını zamanlamadan okuyabilir. Sahte hash ile karşılaştırma
         |   iki yolu da aynı maliyete getiriyor.
         */
        $hash = $user === null ? self::dummyHash() : (string) $user->password;

        if (! Hash::check($password, $hash) || $user === null) {
            throw EmailAuthException::invalidCredentials();
        }

        $user->update(['last_active_at' => $this->clock->now()]);

        return $user->fresh(['profile', 'enrollments.examVariant']) ?? $user;
    }

    /**
     * Var olmayan kullanıcı için karşılaştırılacak sahte hash.
     *
     * Sabit bir değer; amacı doğrulamak değil, bcrypt'in süresini harcamak.
     */
    private static function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('gecersiz-parola-yer-tutucu');
    }
}
