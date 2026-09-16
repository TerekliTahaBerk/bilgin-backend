<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deneme kompozisyonu: "TYT Genel Deneme · 120 soru · 165 dk · yanlış 1/4 götürür".
     *
     * Sınav provası ekranı ve net/puan tahmini tamamen buradan beslenir.
     * LGS'nin farklı ceza oranı (3 yanlış = 1 doğru) yalnızca scoring_rule
     * farkıdır — kod aynı kalır.
     */
    public function up(): void
    {
        Schema::create('exam_blueprints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('exam_section_id')->nullable()->constrained('exam_sections')->nullOnDelete();
            $table->foreignId('exam_variant_id')->nullable()->constrained('exam_variants')->nullOnDelete();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('duration_min');
            $table->jsonb('scoring_rule');              // {"penalty_ratio":0.25,"max_score":500}
            $table->string('status', 16)->default('draft');
            $table->timestamps();
        });

        Schema::create('exam_blueprint_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_blueprint_id')->constrained('exam_blueprints')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('topics')->cascadeOnDelete();
            $table->unsignedSmallInteger('question_count');
            $table->jsonb('difficulty_distribution')->nullable(); // {"1":0.2,"2":0.3,…}
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_blueprint_items');
        Schema::dropIfExists('exam_blueprints');
    }
};
