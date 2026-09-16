<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 21 ders × ~8 ünite × ~6 node ≈ 1000 node. Elle kurulursa içerik ekibi boğulur.
     *
     * Panelde: "Ünite oluştur → şablon seç → konuları işaretle" → node'lar kural,
     * XP ve kilitleriyle hazır gelir. İçerik ekibi yalnızca soru yazar.
     */
    public function up(): void
    {
        Schema::create('unit_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('nodes');        // node tanımları dizisi
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_templates');
    }
};
