<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_hearts', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('hearts');
            // Rejenerasyon tembel hesaplanır (okuma anında). 100k kullanıcıyı
            // dakikada güncelleyen bir cron'a göre hem ucuz hem doğru.
            $table->timestamp('last_regen_at');
            $table->timestamp('unlimited_until')->nullable();
            $table->timestamps();
        });

        // Denetim izi: "canım nereye gitti" sorusunun cevabı ve destek ekranının kaynağı.
        Schema::create('heart_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->smallInteger('delta');
            $table->string('reason', 32);
            $table->unsignedTinyInteger('balance_after');
            // Idempotency anahtarı: "oturum X, soru Y" gibi. Aynı cevap
            // iki kez gönderilse de can yalnızca bir kez düşer.
            $table->string('reference_key', 191)->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
            $table->unique(['user_id', 'reference_key'], 'heart_tx_idempotency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heart_transactions');
        Schema::dropIfExists('user_hearts');
    }
};
