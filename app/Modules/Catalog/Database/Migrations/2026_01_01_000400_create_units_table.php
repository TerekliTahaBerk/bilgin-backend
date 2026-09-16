<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('grade_level')->nullable();  // PathStrategy sıralaması
            $table->string('difficulty_band', 16)->nullable();       // giris|orta|ileri
            $table->string('access', 16)->default('free');
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'sort_order']);
        });

        // Ünite hangi konuları kapsıyor — node'ların selection_rule'u bunu
        // "inherit_from_unit" ile miras alır.
        Schema::create('unit_topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->unsignedSmallInteger('weight')->default(1);
            $table->timestamps();

            $table->unique(['unit_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_topics');
        Schema::dropIfExists('units');
    }
};
