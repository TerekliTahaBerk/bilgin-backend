<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placement_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exam_variant_id')->constrained('exam_variants')->cascadeOnDelete();

            // active | completed | skipped
            $table->string('status', 16)->default('active');

            /*
             | Sorular ATTEMPT'E gömülü, ayrı bir satır tablosunda değil.
             | Seviye testi 12 soruluk ve tek oturumda bitiyor; ikinci bir
             | tablo, hiç sorgulanmayacak bir join'den başka bir şey
             | getirmezdi. Oturum soruları (`session_items`) ayrı tutuluyor
             | çünkü orada soru bazında istatistik okunuyor — burada okunmaz.
             */
            $table->json('questions');

            // Ders bazında doğruluk ve açılan node'lar — sonuç ekranı bunu
            // gösteriyor ve öğrenci "neden buradan başlıyorum" sorusunun
            // cevabını görebiliyor.
            $table->json('result')->nullable();

            $table->unsignedSmallInteger('question_count')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);

            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::table('user_node_progress', function (Blueprint $table): void {
            /*
             | Seviye testiyle atlanan node.
             |
             | `completed = true` yazmak kilidi açmaya yetiyor ama yetmemeli:
             | öğrenci o turu OYNAMADI. Bu damga olmadan ünite yüzdesi,
             | rozetler ve "kaç tur yaptın" istatistiği, yapılmamış işi
             | yapılmış sayardı.
             */
            $table->timestamp('placed_out_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_node_progress', function (Blueprint $table): void {
            $table->dropColumn('placed_out_at');
        });

        Schema::dropIfExists('placement_attempts');
    }
};
