<?php

declare(strict_types=1);

namespace App\Modules\Hearts\Application\UseCase;

use App\Modules\Hearts\Domain\Enum\HeartReason;
use App\Modules\Hearts\Domain\HeartRegeneration;
use App\Modules\Hearts\Domain\Policy\FreeHeartPolicy;
use App\Modules\Hearts\Domain\Policy\HeartPolicy;
use App\Modules\Hearts\Domain\Policy\UnlimitedHeartPolicy;
use App\Modules\Hearts\Infrastructure\Eloquent\Model\HeartTransaction;
use App\Modules\Hearts\Infrastructure\Eloquent\Model\UserHeart;
use App\Shared\Clock\ClockInterface;
use App\Shared\Domain\Entitlement\EntitlementReader;
use App\Shared\Domain\Hearts\HeartBalance;
use App\Shared\Domain\Hearts\HeartBalanceReader;
use App\Shared\Domain\Hearts\HeartConsumer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Hearts modülünün dışarıya açtığı yüzey.
 *
 * İki ayrı arayüzü birden uygular ama çağıranlar yalnızca ihtiyaç duyduklarını
 * enjekte eder (ISP): Learning `HeartConsumer`, profil ekranı `HeartBalanceReader`.
 */
final readonly class HeartService implements HeartBalanceReader, HeartConsumer
{
    public function __construct(
        private ClockInterface $clock,
        private EntitlementReader $entitlements,
        private HeartRegeneration $regeneration,
    ) {}

    public function balanceFor(int $userId): HeartBalance
    {
        $policy = $this->policyFor($userId);

        if (! $policy->consumesHearts()) {
            return HeartBalance::unlimited($this->regeneration->max());
        }

        $record = $this->regenerated($userId);

        return $this->toBalance($record);
    }

    public function consume(int $userId, ?string $referenceKey = null): HeartBalance
    {
        $policy = $this->policyFor($userId);

        // Premium'da no-op. Çağıran tarafta `if (premium)` yok.
        if (! $policy->consumesHearts()) {
            return HeartBalance::unlimited($this->regeneration->max());
        }

        return DB::transaction(function () use ($userId, $referenceKey): HeartBalance {
            // lockForUpdate aynı kullanıcının eşzamanlı isteklerini sıraya sokar;
            // idempotency kontrolü de bu kilidin içinde yapıldığı için yarış yok.
            $record = $this->regenerated($userId, lock: true);

            if ($referenceKey !== null && $this->alreadyApplied($userId, $referenceKey)) {
                return $this->toBalance($record);
            }

            if ($record->hearts <= 0) {
                return $this->toBalance($record);
            }

            $record->hearts--;
            $record->save();

            $this->log($userId, -1, HeartReason::WrongAnswer, $record->hearts, $referenceKey);

            return $this->toBalance($record);
        });
    }

    /** Ödüllü reklam, pratik turu veya destek ekibi tarafından can ekler. */
    public function grant(int $userId, HeartReason $reason, ?string $referenceKey = null): HeartBalance
    {
        return DB::transaction(function () use ($userId, $reason, $referenceKey): HeartBalance {
            $record = $this->regenerated($userId, lock: true);

            if ($referenceKey !== null && $this->alreadyApplied($userId, $referenceKey)) {
                return $this->toBalance($record);
            }

            if ($record->hearts < $this->regeneration->max()) {
                $record->hearts++;
                $record->save();

                $this->log($userId, 1, $reason, $record->hearts, $referenceKey);
            }

            return $this->toBalance($record);
        });
    }

    private function policyFor(int $userId): HeartPolicy
    {
        return $this->entitlements->for($userId)->premium
            ? new UnlimitedHeartPolicy
            : new FreeHeartPolicy;
    }

    /** Kaydı okur, rejenerasyonu uygular ve değiştiyse saklar. */
    private function regenerated(int $userId, bool $lock = false): UserHeart
    {
        $now = $this->clock->now();

        $query = UserHeart::query()->where('user_id', $userId);
        $lock && $query->lockForUpdate();

        $record = $query->first();

        if ($record === null) {
            return UserHeart::query()->create([
                'user_id' => $userId,
                'hearts' => $this->regeneration->max(),
                'last_regen_at' => $now,
            ]);
        }

        $result = $this->regeneration->apply(
            $record->hearts,
            $record->last_regen_at->toDateTimeImmutable(),
            $now,
        );

        if ($result->hearts !== $record->hearts || $result->lastRegenAt->getTimestamp() !== $record->last_regen_at->getTimestamp()) {
            $record->hearts = $result->hearts;
            // Carbon'a çevrilerek atanır: model özelliği CarbonImmutable tipli,
            // domain ise saf DateTimeImmutable üretir. Dönüşüm sınırda yapılır.
            $record->last_regen_at = CarbonImmutable::instance($result->lastRegenAt);
            $record->save();

            if ($result->changed()) {
                $this->log($userId, $result->earned, HeartReason::Regen, $result->hearts, null);
            }
        }

        return $record;
    }

    private function toBalance(UserHeart $record): HeartBalance
    {
        $lastRegen = $record->last_regen_at->toDateTimeImmutable();

        return new HeartBalance(
            hearts: $record->hearts,
            max: $this->regeneration->max(),
            unlimited: false,
            nextHeartAt: $this->regeneration->nextHeartAt($record->hearts, $lastRegen),
            fullAt: $this->regeneration->fullAt($record->hearts, $lastRegen),
        );
    }

    private function alreadyApplied(int $userId, string $referenceKey): bool
    {
        return HeartTransaction::query()
            ->where('user_id', $userId)
            ->where('reference_key', $referenceKey)
            ->exists();
    }

    private function log(int $userId, int $delta, HeartReason $reason, int $balanceAfter, ?string $referenceKey): void
    {
        HeartTransaction::query()->create([
            'user_id' => $userId,
            'delta' => $delta,
            'reason' => $reason,
            'balance_after' => $balanceAfter,
            'reference_key' => $referenceKey,
            'created_at' => $this->clock->now(),
        ]);
    }
}
