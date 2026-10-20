<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCase;

use App\Modules\Identity\Domain\Enum\DailyGoal;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use Illuminate\Support\Facades\DB;

/**
 * Ayarlar ekranının kısmi güncellemesi.
 *
 * Gelen dizide OLMAYAN anahtar dokunulmadan kalıyor — `null` ile "yok" ile
 * "değiştirme" birbirine karışmasın diye `array_key_exists` kullanılıyor.
 * `avatar_key` ve `reminder_time` gerçekten `null`'a çekilebilen alanlar;
 * `isset` burada sessizce yanlış davranırdı.
 */
final readonly class UpdateSettings
{
    /** @param  array<string, mixed>  $changes */
    public function __invoke(int $userId, array $changes): User
    {
        return DB::transaction(function () use ($userId, $changes): User {
            $user = User::query()->findOrFail($userId);

            $userFields = array_intersect_key(
                $changes,
                array_flip(['name', 'avatar_key', 'timezone']),
            );

            if ($userFields !== []) {
                $user->update($userFields);
            }

            $profileFields = [];

            foreach (['reminder_enabled', 'reminder_time'] as $key) {
                if (array_key_exists($key, $changes)) {
                    $profileFields[$key] = $changes[$key];
                }
            }

            if (array_key_exists('daily_goal_rounds', $changes)) {
                $profileFields['daily_goal_rounds'] = DailyGoal::from(
                    (int) $changes['daily_goal_rounds'],
                );
            }

            /*
             | Hatırlatma kapatılırken saat SİLİNMİYOR: öğrenci yarın tekrar
             | açtığında eski saatini bulsun. Açıkken saatin boş kalması ise
             | bildirimin hiç kurulamaması demek — varsayılana düşüyor.
             */
            if (($profileFields['reminder_enabled'] ?? null) === true
                && $user->profile?->reminder_time === null
                && ($profileFields['reminder_time'] ?? null) === null) {
                $profileFields['reminder_time'] = '19:00';
            }

            if ($profileFields !== []) {
                $user->profile()->updateOrCreate(['user_id' => $user->id], $profileFields);
            }

            return $user->fresh(['profile', 'enrollments.examVariant']) ?? $user;
        });
    }
}
