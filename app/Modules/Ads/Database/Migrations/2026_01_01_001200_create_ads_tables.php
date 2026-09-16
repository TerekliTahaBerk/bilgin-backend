<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_rewards', function (Blueprint $table): void {
            $table->id();
            /*
             | nullable: callback kullanıcı çözülmeden ÖNCE kaydedilir.
             | Bilinmeyen bir user_id ile gelen istek de saklanmalı — "reklamı
             | izledim ama can gelmedi" şikâyetinin izi ancak böyle kalır.
             */
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('network', 32)->default('admob');
            $table->string('placement', 32);              // heart_refill | streak_freeze
            /*
             | transaction_id UNIQUE — ödülün iki kez verilmesini engelleyen
             | tek şey bu. AdMob ağ hatasında aynı callback'i tekrar gönderir;
             | tekrarın ödül üretmesi, reklam izlemeden can kazanmanın yolu olur.
             */
            $table->string('transaction_id')->unique();
            $table->string('ad_unit')->nullable();
            $table->unsignedSmallInteger('reward_amount')->default(1);
            $table->string('reward_item', 64)->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->string('rejection_reason', 64)->nullable();
            $table->timestamp('received_at');

            // Günlük limit sorgusu: kullanıcının bugünkü ödülleri.
            $table->index(['user_id', 'granted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_rewards');
    }
};
