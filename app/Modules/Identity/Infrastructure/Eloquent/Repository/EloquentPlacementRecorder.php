<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent\Repository;

use App\Modules\Identity\Infrastructure\Eloquent\Model\UserProfile;
use App\Shared\Clock\ClockInterface;
use App\Shared\Domain\Learner\PlacementRecorder;

final readonly class EloquentPlacementRecorder implements PlacementRecorder
{
    public function __construct(private ClockInterface $clock) {}

    public function markCompleted(int $userId): void
    {
        $profile = UserProfile::query()->firstOrCreate(['user_id' => $userId]);

        /*
         | İlk damga korunuyor. Öğrenci testi tekrar çözerse "ne zaman
         | seviyelendirildi" sorusunun cevabı ilk seferdir; sonraki
         | denemeler `placement_attempts` tablosunda zaten duruyor.
         */
        if ($profile->placement_completed_at !== null) {
            return;
        }

        $profile->update(['placement_completed_at' => $this->clock->now()]);
    }

    public function hasCompleted(int $userId): bool
    {
        return UserProfile::query()
            ->where('user_id', $userId)
            ->whereNotNull('placement_completed_at')
            ->exists();
    }
}
