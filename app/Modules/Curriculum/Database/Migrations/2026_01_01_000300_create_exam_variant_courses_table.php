<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SİSTEMİN KALBİ — müfredat ile içeriği bağlayan eşleme tablosu.
     *
     * "AYT Matematik hem Sayısal'da hem EA'da" ifadesi burada iki satırdır,
     * iki içerik kopyası değil. Ders sırası, hangi sekmede görüneceği ve
     * free/premium durumu course'ta değil BURADA tutulur; çünkü bunların
     * hepsi varyanta göre değişir.
     *
     * Panelden düzenlenebilir olması, alan müfredatını değiştirmeyi bir
     * deploy işi olmaktan çıkarır.
     */
    public function up(): void
    {
        Schema::create('exam_variant_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_variant_id')->constrained('exam_variants')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            // Hangi sekmede görünecek. Course'ta değil burada: aynı course
            // farklı sınavda farklı oturuma denk gelebilir.
            $table->foreignId('exam_section_id')->constrained('exam_sections')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->string('access', 16)->default('free');       // free|premium
            $table->unsignedSmallInteger('exam_weight')->nullable(); // bu varyantta kaç soru
            $table->string('placeholder_label')->nullable();     // "Yakında"
            $table->timestamps();

            $table->unique(['exam_variant_id', 'course_id']);
            $table->index(['exam_variant_id', 'exam_section_id', 'sort_order'], 'evc_variant_section_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_variant_courses');
    }
};
