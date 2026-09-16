<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deneme oturumları bir üniteye ait DEĞİLDİR.
     *
     * "TYT Genel Deneme" 9 dersten soru toplar; tek bir unit_node'a
     * bağlanamaz. Bu yüzden node/unit nullable oluyor ve yerine
     * exam_blueprint_id geliyor. Bir oturum ya node'a ya blueprint'e aittir.
     */
    public function up(): void
    {
        Schema::table('study_sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('unit_node_id')->nullable()->change();
            $table->unsignedBigInteger('unit_id')->nullable()->change();
            $table->unsignedBigInteger('course_id')->nullable()->change();

            $table->foreignId('exam_blueprint_id')->nullable()->after('unit_id')
                ->constrained('exam_blueprints')->nullOnDelete();

            // Deneme sonucu: net ve tahmini puan.
            $table->decimal('net', 6, 2)->nullable()->after('accuracy');
            $table->decimal('estimated_score', 7, 2)->nullable()->after('net');
        });

        // Denemede yapılan yanlışların da tekrar kuyruğuna girmesi için:
        // deneme bir üniteye ait değil, o yüzden unit_id nullable olmalı.
        // "Hızlı Tekrar" node'u üniteye göre sorguladığı için bunları almaz;
        // ileride gelecek "Yanlışlarım" ekranı alacak.
        Schema::table('review_queue', function (Blueprint $table): void {
            $table->unsignedBigInteger('unit_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('study_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('exam_blueprint_id');
            $table->dropColumn(['net', 'estimated_score']);
        });
    }
};
