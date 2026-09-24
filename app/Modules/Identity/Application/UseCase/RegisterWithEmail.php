<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCase;

use App\Modules\Identity\Domain\Exception\EmailAuthException;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Modules\Identity\Notification\VerifyEmailNotification;
use App\Shared\Clock\ClockInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * E-posta ve şifreyle kayıt.
 *
 * MİSAFİR İLERLEMESİ KORUNUR. Kullanıcı misafir token'ıyla gelirse yeni
 * hesap açılmaz, var olan hesap kalıcıya çevrilir — XP'si, serisi ve
 * tamamladığı adımlar yerinde kalır. Yeni hesap açmak, uygulamayı deneyip
 * beğenen kullanıcıyı tam kaydolduğu anda sıfırlamak olurdu.
 */
final readonly class RegisterWithEmail
{
    public function __construct(private ClockInterface $clock) {}

    public function __invoke(
        string $email,
        string $password,
        ?string $name = null,
        ?int $guestUserId = null,
    ): User {
        $email = mb_strtolower(trim($email));

        $existing = User::query()->where('email', $email)->first();
        $guest = $this->guestFor($guestUserId);

        if ($existing !== null) {
            // Misafirin ilerlemesi varken onu sessizce çöpe atmıyoruz;
            // kullanıcı hangisini istediğine kendisi karar versin.
            throw $guest !== null && $this->hasProgress($guest)
                ? EmailAuthException::progressWouldBeLost()
                : EmailAuthException::emailTaken();
        }

        if ($guestUserId !== null && $guest === null) {
            // Token kalıcı bir hesaba ait: ikinci hesap açmak, kullanıcının
            // hangi hesapta olduğunu kaybetmesine yol açar.
            throw EmailAuthException::alreadyRegistered();
        }

        $user = $guest !== null
            ? $this->upgradeGuest($guest, $email, $password, $name)
            : $this->createUser($email, $password, $name);

        $this->sendVerification($user);

        return $user;
    }

    private function guestFor(?int $userId): ?User
    {
        if ($userId === null) {
            return null;
        }

        return User::query()->where('id', $userId)->where('is_guest', true)->first();
    }

    /** Kaybedilmesi kullanıcıyı üzecek bir şey var mı. */
    private function hasProgress(User $guest): bool
    {
        return DB::table('xp_ledger')->where('user_id', $guest->id)->exists();
    }

    private function upgradeGuest(User $guest, string $email, string $password, ?string $name): User
    {
        return DB::transaction(function () use ($guest, $email, $password, $name): User {
            $guest->update([
                'is_guest' => false,
                'email' => $email,
                // Kullanıcı kayıt akışında kendi adını girmiş olabilir;
                // formdan gelen ad onu EZMEZ.
                'name' => $guest->name ?? $name,
                'password' => Hash::make($password),
                'email_verified_at' => null,
                'last_active_at' => $this->clock->now(),
            ]);

            $guest->identities()->updateOrCreate(
                ['provider' => 'email', 'provider_user_id' => $email],
                ['email' => $email],
            );

            return $guest->fresh(['profile', 'enrollments.examVariant']) ?? $guest;
        });
    }

    private function createUser(string $email, string $password, ?string $name): User
    {
        return DB::transaction(function () use ($email, $password, $name): User {
            $user = User::query()->create([
                'is_guest' => false,
                'email' => $email,
                'name' => $name,
                'password' => Hash::make($password),
                'last_active_at' => $this->clock->now(),
            ]);

            $user->identities()->create([
                'provider' => 'email',
                'provider_user_id' => $email,
                'email' => $email,
            ]);

            return $user;
        });
    }

    /**
     * Doğrulama e-postasını gönderir — ama gönderilemezse KAYDI DÜŞÜRMEZ.
     *
     * Posta sağlayıcısındaki bir kesinti yüzünden kayıt olamamak, doğrulanmamış
     * bir hesaptan çok daha kötü. Hesap zaten çalışıyor; doğrulama sonradan
     * istenebilir.
     */
    private function sendVerification(User $user): void
    {
        try {
            $user->notify(new VerifyEmailNotification);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
