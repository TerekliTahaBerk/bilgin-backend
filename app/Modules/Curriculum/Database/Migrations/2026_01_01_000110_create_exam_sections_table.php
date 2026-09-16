<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sınav oturumu. Tasarımdaki üst sekme (TYT / AYT) doğrudan buradan gelir.
     * LGS'de sozel/sayisal, KPSS'te gy_gk/egitim_bilimleri olarak kullanılır.
     */
    public function up(): void
    {
        Schema::create('exam_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->string('code', 32);                 // tyt, ayt, ydt
            $table->string('name');
            $table->unsignedSmallInteger('duration_min')->nullable();
            $table->unsignedSmallInteger('question_count')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['exam_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sections');
    }
};
