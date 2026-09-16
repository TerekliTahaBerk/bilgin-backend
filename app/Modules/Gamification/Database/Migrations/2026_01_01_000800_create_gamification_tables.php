<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | XP DEFTERİ — tek doğruluk kaynağı, append-only.
         |
         | unique(source_type, source_id) sayesinde retry veya çift istek
         | matematiksel olarak çift XP üretemez. user_stats.total_xp bu
         | defterden TÜRETİLİR; tutarsızlık şüphesinde yeniden inşa edilebilir.
         */
        Schema::create('xp_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('amount');
            $table->string('source_type', 32);          // session|challenge|badge|quest
            $table->unsignedBigInteger('source_id');
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            // Lig XP'si yalnızca oturumdan gelir; rozet/görev XP'si sayılmaz.
            // Manipülasyon yüzeyini daraltır.
            $table->boolean('counts_for_league')->default(true);
            $table->jsonb('breakdown')->nullable();      // {"base":20,"perfect":20,...}
            $table->timestamp('awarded_at');

            $table->unique(['source_type', 'source_id']);
            $table->index(['user_id', 'awarded_at']);
        });

        Schema::create('user_stats', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('total_xp')->default(0);
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedSmallInteger('current_streak')->default(0);
            $table->unsignedSmallInteger('longest_streak')->default(0);
            // Kullanıcının YEREL tarihine göre; gün sınırı saat diliminde değişir.
            $table->date('last_study_date')->nullable();
            $table->unsignedInteger('total_sessions')->default(0);
            $table->unsignedInteger('perfect_sessions')->default(0);
            $table->unsignedInteger('total_correct')->default(0);
            $table->unsignedInteger('total_study_seconds')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_stats');
        Schema::dropIfExists('xp_ledger');
    }
};
