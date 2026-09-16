<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bir dersin belirli bir kapsamdaki müfredatı: "TYT Temel Matematik".
     *
     * Course HİÇBİR sınav varyantına ait değildir — bağımsızdır. SAY, EA, SÖZ,
     * DİL ve ileride DGS onu exam_variant_courses üzerinden referans alır,
     * kopyalamaz. İçerik (units → nodes → exercises) burada yaşar.
     */
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->string('code', 64)->unique();       // tyt_matematik
            $table->string('name');                     // "TYT Temel Matematik"
            $table->string('short_name')->nullable();   // "Matematik" (ders kartında)
            $table->string('scope', 16);                // tyt|ayt|ydt|lgs|…
            $table->string('icon', 64)->nullable();
            $table->string('color', 9)->nullable();
            $table->foreignId('default_section_id')->nullable()
                ->constrained('exam_sections')->nullOnDelete();
            $table->jsonb('grade_range')->nullable();   // ["11","12","mezun"]
            $table->unsignedSmallInteger('effective_from_year')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['scope', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
