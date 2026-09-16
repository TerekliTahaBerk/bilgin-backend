<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konu ağacı — SUBJECT'e bağlı, course'a değil.
     *
     * Bu tek karar sayesinde TYT Matematik ve AYT Matematik aynı konu ağacını
     * paylaşır: öğrencinin "Bileşke Fonksiyon" ustalığı tek yerde birikir ve
     * TYT'den AYT'ye geçerken taşınır.
     */
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->string('code', 96);
            $table->string('name');
            $table->unsignedTinyInteger('grade_level')->nullable();   // 8..12
            $table->unsignedSmallInteger('exam_weight')->nullable();  // tahmini soru adedi
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['subject_id', 'code']);
            $table->index(['subject_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
