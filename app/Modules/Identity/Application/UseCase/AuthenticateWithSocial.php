<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCase;

use App\Modules\Identity\Domain\Enum\SocialProvider;
use App\Modules\Identity\Domain\Social\SocialIdentity;
use App\Modules\Identity\Domain\Social\VerifierRegistry;
use App\Modules\Identity\Infrastructure\Eloquent\Model\AuthIdentity;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Shared\Clock\ClockInterface;
use Illuminate\Support\Facades\DB;

/**
 * Apple / Google ile giriş ve misafir hesabı kalıcıya çevirme.
 *
 * En kritik karar burada: misafir olarak ilerleme kaydetmiş biri, zaten
 * hesabı olan bir Apple kimliğiyle giriş yaparsa İKİ ilerleme vardır ve
 * biri kaybolacaktır.
 *
 * Bu durumu sessizce çözmüyoruz. İlerlemeleri birleştirmek (XP defteri,
 * seri, can, abonelik) doğru yapılması çok zor ve yanlış yapıldığında
 * sessizce yanlış veri üretir. Onun yerine çakışmayı KULLANICIYA sorup
 * açık bir tercih bekliyoruz — hangi ilerlemenin kaybolacağına o karar verir.
 */
final readonly class AuthenticateWithSocial
{
    public function __construct(
        private ClockInterface $clock,
        private VerifierRegistry $verifiers,
    ) {}

    public function __invoke(
        SocialProvider $provider,
        string $identityToken,
        ?int $currentUserId = null,
        bool $discardGuestProgress = false,
    ): SocialAuthResult {
        $identity = $this->verifiers->for($provider)->verify($identityToken);

        $existing = $this->findByIdentity($identity);
        $guest = $this->activeGuest($currentUserId);

        // Bu kimlikle daha önce hesap açılmış.
        if ($existing !== null) {
            if ($guest !== null && $guest->id !== $existing->id && ! $discardGuestProgress) {
                return SocialAuthResult::conflict(
                    guest: $this->summarize($guest),
                    existing: $this->summarize($existing),
                );
            }

            $this->touch($existing, $identity);

            return SocialAuthResult::signedIn($existing, isNew: false);
        }

        // Kimlik yeni: misafir varsa onu yükselt, yoksa hesap aç.
        return $guest !== null
            ? SocialAuthResult::signedIn($this->upgradeGuest($guest, $identity), isNew: false, upgraded: true)
            : SocialAuthResult::signedIn($this->createUser($identity), isNew: true);
    }

    private function findByIdentity(SocialIdentity $identity): ?User
    {
        return AuthIdentity::query()
            ->where('provider', $identity->provider->value)
            ->where('provider_user_id', $identity->providerUserId)
            ->first()?->user;
    }

    /** Yalnızca gerçekten misafir olan ve ilerlemesi olabilecek hesap. */
    private function activeGuest(?int $userId): ?User
    {
        if ($userId === null) {
            return null;
        }

        return User::query()->where('id', $userId)->where('is_guest', true)->first();
    }

    private function upgradeGuest(User $guest, SocialIdentity $identity): User
    {
        return DB::transaction(function () use ($guest, $identity): User {
            $guest->identities()->create([
                'provider' => $identity->provider->value,
                'provider_user_id' => $identity->providerUserId,
                'email' => $identity->email,
            ]);

            $guest->update([
                'is_guest' => false,
                // Kullanıcı onboarding'de kendi adını girmiş olabilir; sağlayıcıdan
                // gelen ad onu EZMEZ. Lig sıralamasında görünen ad onun tercihi.
                'name' => $guest->name ?? $identity->name,
                'email' => $identity->emailIsTrustworthy() ? $identity->email : $guest->email,
                'email_verified_at' => $identity->emailIsTrustworthy() ? $this->clock->now() : null,
                'last_active_at' => $this->clock->now(),
            ]);

            return $guest->fresh(['profile', 'enrollments.examVariant']) ?? $guest;
        });
    }

    private function createUser(SocialIdentity $identity): User
    {
        return DB::transaction(function () use ($identity): User {
            $user = User::query()->create([
                'is_guest' => false,
                'name' => $identity->name,
                'email' => $identity->emailIsTrustworthy() ? $identity->email : null,
                'email_verified_at' => $identity->emailIsTrustworthy() ? $this->clock->now() : null,
                'last_active_at' => $this->clock->now(),
            ]);

            $user->identities()->create([
                'provider' => $identity->provider->value,
                'provider_user_id' => $identity->providerUserId,
                'email' => $identity->email,
            ]);

            return $user;
        });
    }

    private function touch(User $user, SocialIdentity $identity): void
    {
        $user->update(['last_active_at' => $this->clock->now()]);

        // Apple'da kullanıcı "e-postamı gizle" seçeneğini sonradan kapatabilir;
        // kimlik kaydındaki e-posta güncel tutulur.
        $user->identities()
            ->where('provider', $identity->provider->value)
            ->update(['email' => $identity->email]);
    }

    private function summarize(User $user): AccountSummary
    {
        $stats = DB::table('user_stats')->where('user_id', $user->id)->first();

        return new AccountSummary(
            uuid: (string) $user->uuid,
            name: $user->name,
            isGuest: (bool) $user->is_guest,
            totalXp: (int) ($stats->total_xp ?? 0),
            level: (int) ($stats->level ?? 1),
            currentStreak: (int) ($stats->current_streak ?? 0),
        );
    }
}
