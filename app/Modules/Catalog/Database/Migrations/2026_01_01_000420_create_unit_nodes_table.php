<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Yol (path) düğümü — SABİT SORU LİSTESİ DEĞİL, TARİF.
     *
     * selection_rule, soruların havuzdan nasıl seçileceğini anlatır. Mini
     * Challenge, Hızlı Tekrar, Ünite Challenge ve Sınav Provası ayrı özellikler
     * değil; aynı motorun farklı kurallarıdır. Yeni bir oyun modu çoğu zaman
     * yeni bir JSON kuralıdır, yeni kod değil.
     *
     * unlock_rule composite bir kural ağacıdır (AllOf/AnyOf/MinAccuracy…);
     * içerik ekibi panelden kurgular, kod deploy'u gerekmez.
     */
    public function up(): void
    {
        Schema::create('unit_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('title');
            $table->string('node_type', 32);
            $table->string('difficulty', 32);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('exercise_count');
            $table->unsignedSmallInteger('time_limit_sec')->nullable();
            $table->boolean('consumes_hearts')->default(true);
            $table->unsignedSmallInteger('xp_reward');
            $table->string('access', 16)->default('free');
            $table->jsonb('selection_rule');
            $table->jsonb('unlock_rule')->nullable();
            $table->string('preview_label')->nullable();   // "boşluk doldur · 6 soru"
            $table->string('status', 16)->default('draft');
            $table->timestamps();

            $table->index(['unit_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_nodes');
    }
};
