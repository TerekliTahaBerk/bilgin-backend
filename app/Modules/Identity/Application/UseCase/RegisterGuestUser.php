<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCase;

use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Shared\Clock\ClockInterface;
use Illuminate\Support\Facades\DB;

/**
 * Misafir hesap açar. Tasarımda onboarding "Başla" ile başlıyor — kayıt ekranı
 * yok. Kullanıcı önce oynar, hesabını sonra bağlar (LinkAccount).
 *
 * Aynı cihazdan ikinci kez çağrılırsa mevcut misafir hesabı döner: uygulamayı
 * kapatıp açan kullanıcı ilerlemesini kaybetmemeli.
 */
final readonly class RegisterGuestUser
{
    public function __construct(private ClockInterface $clock) {}

    public function __invoke(string $deviceIdentifier, string $platform, ?string $appVersion = null): User
    {
        return DB::transaction(function () use ($deviceIdentifier, $platform, $appVersion): User {
            $now = $this->clock->now();

            // Soru "bu cihazın kullanıcısı kim" — cihazı bulup kullanıcısına
            // gitmek yerine doğrudan kullanıcıyı sormak hem daha net hem de
            // yetim cihaz kaydı ihtimalini ortadan kaldırır.
            $existing = User::query()
                ->where('is_guest', true)
                ->whereHas('devices', fn ($q) => $q->where('device_identifier', $deviceIdentifier))
                ->first();

            if ($existing !== null) {
                $existing->devices()
                    ->where('device_identifier', $deviceIdentifier)
                    ->update(['last_seen_at' => $now, 'app_version' => $appVersion]);

                $existing->update(['last_active_at' => $now]);

                return $existing;
            }

            $user = User::query()->create([
                'is_guest' => true,
                'last_active_at' => $now,
            ]);

            $user->devices()->create([
                'platform' => $platform,
                'device_identifier' => $deviceIdentifier,
                'app_version' => $appVersion,
                'last_seen_at' => $now,
            ]);

            return $user;
        });
    }
}
