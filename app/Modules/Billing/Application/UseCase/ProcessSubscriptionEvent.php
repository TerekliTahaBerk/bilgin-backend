<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\UseCase;

use App\Modules\Billing\Application\DTO\SubscriptionEvent;
use App\Modules\Billing\Domain\Enum\SubscriptionStatus;
use App\Modules\Billing\Infrastructure\Eloquent\Model\Subscription;
use App\Modules\Billing\Infrastructure\Eloquent\Model\SubscriptionEventLog;
use App\Modules\Billing\Infrastructure\Eloquent\Repository\EloquentEntitlementReader;
use App\Modules\Identity\Infrastructure\Eloquent\Model\User;
use App\Shared\Clock\ClockInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Webhook olayını işler.
 *
 * Üç güvence:
 *   1. Ham payload HER ZAMAN saklanır — işleyemesek bile. Bir abonelik
 *      şikâyetinde "sağlayıcı bize ne gönderdi" sorusunun cevabı budur.
 *   2. provider_event_id UNIQUE — sağlayıcının yeniden denemeleri çift
 *      işlenmez. Çift işleme, ücretsiz kullanıcıya premium vermek ya da
 *      premium'u erken bitirmek demektir.
 *   3. Bilinmeyen kullanıcı veya olay tipi HATA DEĞİLDİR; kaydedilir ve
 *      200 dönülür. 500 dönmek sağlayıcının saatlerce yeniden denemesine
 *      ve kuyruğun tıkanmasına yol açar.
 */
final readonly class ProcessSubscriptionEvent
{
    public function __construct(
        private ClockInterface $clock,
        private Cache $cache,
    ) {}

    /** @param  array<string, mixed>  $rawPayload */
    public function __invoke(?SubscriptionEvent $event, array $rawPayload): ProcessResult
    {
        $now = $this->clock->now();

        // Ayrıştırılamayan olay bile kaydedilir.
        if ($event === null) {
            SubscriptionEventLog::query()->create([
                'provider_event_id' => 'unparsed:'.hash('sha256', json_encode($rawPayload) ?: '').':'.$now->getTimestamp(),
                'type' => (string) ($rawPayload['event']['type'] ?? 'unknown'),
                'payload' => $rawPayload,
                'received_at' => $now,
                'error' => 'Ayrıştırılamadı veya ilgilenmediğimiz bir olay tipi.',
            ]);

            return ProcessResult::ignored('Olay tipi işlenmiyor.');
        }

        /*
         | insertOrIgnore (ON CONFLICT DO NOTHING) kullanılıyor, unique
         | ihlalini yakalamak yerine.
         |
         | Postgres'te BAŞARISIZ bir INSERT içinde bulunduğu transaction'ı
         | komple iptal eder; sonraki her sorgu "transaction is aborted" ile
         | patlar. Yani "dene ve yakala" deseni, çift olayı idempotent
         | işlemek yerine isteğin tamamını düşürürdü.
         */
        $inserted = DB::table('subscription_events')->insertOrIgnore([
            'provider_event_id' => $event->providerEventId,
            'type' => $event->type,
            'payload' => json_encode($rawPayload),
            'received_at' => $now,
        ]);

        if ($inserted === 0) {
            return ProcessResult::duplicate();
        }

        $log = SubscriptionEventLog::query()
            ->where('provider_event_id', $event->providerEventId)
            ->firstOrFail();

        // app_user_id DIŞ GİRDİDİR. UUID biçiminde olmayan bir değeri
        // doğrudan uuid kolonunda sorgulamak veritabanı hatası üretir ve
        // webhook 500 döner — sağlayıcı da saatlerce yeniden dener.
        $user = Str::isUuid($event->appUserId)
            ? User::query()->where('uuid', $event->appUserId)->first()
            : null;

        if ($user === null) {
            $log->update(['error' => "Kullanıcı bulunamadı: {$event->appUserId}"]);

            return ProcessResult::ignored('Kullanıcı bulunamadı.');
        }

        DB::transaction(function () use ($event, $user, $log, $now): void {
            Subscription::query()->updateOrCreate(
                ['user_id' => $user->id, 'product_id' => $event->productId],
                [
                    'provider' => 'revenuecat',
                    'provider_subscription_id' => $event->providerSubscriptionId,
                    'plan' => $event->plan,
                    'status' => $event->status,
                    'store' => $event->store,
                    'started_at' => $event->occurredAt,
                    'current_period_end' => $this->periodEnd($event),
                    'trial_end' => $event->trialEnd,
                    'cancelled_at' => $event->status === SubscriptionStatus::Cancelled ? $event->occurredAt : null,
                ],
            );

            $log->update(['user_id' => $user->id, 'processed_at' => $now]);
        });

        // Hak değişti: önbellek anında düşürülür, yoksa kullanıcı 5 dakika
        // boyunca satın aldığı premium'u göremezdi.
        $this->cache->forget(EloquentEntitlementReader::cacheKey((int) $user->id));

        return ProcessResult::processed($event->status->value);
    }

    /**
     * İade edilen abonelik ANINDA biter.
     *
     * Sağlayıcı iade olayında bitiş tarihini geleceği gösterecek şekilde
     * gönderebilir; ona güvenmek, parasını geri almış kullanıcının premium
     * kullanmaya devam etmesi demektir.
     */
    private function periodEnd(SubscriptionEvent $event): ?\DateTimeImmutable
    {
        return $event->status->revokesImmediately()
            ? $event->occurredAt
            : $event->expiresAt;
    }
}
