<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Öğrencinin kaydolduğu şey. Onboarding'in 1. (sınav) ve 2. (alan) adımı
     * tek bir varyanta çözülür: yks + say → yks_say.
     *
     * ÖNEMLİ: hiçbir ilerleme tablosu bu tabloya bağlı DEĞİLDİR. Alan değişimi
     * user_enrollments'ta tek satırlık update'tir; öğrenilen hiçbir şey kaybolmaz.
     */
    public function up(): void
    {
        Schema::create('exam_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->string('code', 48)->unique();       // yks_say, yks_ea, lgs
            $table->string('name');                     // "YKS · Sayısal"
            $table->string('field_code', 16);           // say|ea|soz|dil|undecided
            $table->string('description')->nullable();  // "Matematik · Fizik · Kimya · Biyoloji"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['exam_id', 'field_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_variants');
    }
};
