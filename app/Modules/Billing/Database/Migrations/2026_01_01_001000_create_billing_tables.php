<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32)->default('revenuecat');
            $table->string('provider_subscription_id')->nullable();
            $table->string('product_id');
            $table->string('plan', 16);                    // monthly|yearly
            $table->string('status', 16);                  // trial|active|grace|expired|cancelled|refunded
            $table->string('store', 16)->nullable();       // app_store|play_store
            $table->timestamp('started_at');
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // Bir kullanıcının aynı üründen iki aktif aboneliği olamaz.
            $table->unique(['user_id', 'product_id']);
            $table->index(['user_id', 'status']);
        });

        /*
         | Webhook ham kaydı.
         |
         | provider_event_id UNIQUE: RevenueCat aynı olayı tekrar gönderebilir
         | (kendi yeniden deneme politikası). Çift işleme, ücretsiz kullanıcıya
         | premium vermek veya premium'u erken bitirmek demektir.
         |
         | Ham payload saklanır: bir abonelik sorunu bildirildiğinde "sağlayıcı
         | bize ne gönderdi" sorusunun cevabı olmadan hata ayıklanamaz.
         */
        Schema::create('subscription_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32)->default('revenuecat');
            $table->string('provider_event_id')->unique();
            $table->string('type', 48);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('received_at');

            $table->index(['user_id', 'received_at']);
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscriptions');
    }
};
