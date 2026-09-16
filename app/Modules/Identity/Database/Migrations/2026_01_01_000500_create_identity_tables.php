<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Apple Sign-In App Store kuralı gereği zorunlu; email/şifre opsiyonel.
        Schema::create('auth_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 16);              // apple|google|email
            $table->string('provider_user_id');
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->index('user_id');
        });

        // Onboarding'in 3–7. adımlarının çıktısı.
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('grade', 8)->nullable();       // 8|9|10|11|12|mezun
            $table->unsignedSmallInteger('target_exam_year')->nullable();
            $table->unsignedTinyInteger('target_exam_month')->nullable();
            $table->unsignedTinyInteger('daily_goal_rounds')->default(3);  // 1|3|6
            $table->boolean('reminder_enabled')->default(true);
            $table->time('reminder_time')->nullable();   // 17:00|20:00|22:00
            $table->string('acquisition_source', 32)->nullable();
            $table->timestamp('placement_completed_at')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamps();
        });

        /**
         * Öğrencinin kaydolduğu sınav varyantı.
         *
         * KRİTİK: hiçbir ilerleme tablosu buraya bağlı DEĞİLDİR. İlerleme
         * course_id'ye bağlıdır. Böylece Sayısal'dan EA'ya geçiş tek satırlık
         * bir update'tir ve öğrencinin TYT'de öğrendiği hiçbir şey kaybolmaz.
         */
        Schema::create('user_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exam_variant_id')->constrained('exam_variants')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->timestamp('enrolled_at');
            $table->timestamp('field_changed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'exam_variant_id']);
            $table->index(['user_id', 'is_primary']);
        });

        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('platform', 16);              // ios|android
            $table->string('push_token')->nullable();
            $table->string('device_identifier')->nullable();
            $table->string('app_version', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
        Schema::dropIfExists('user_enrollments');
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('auth_identities');
    }
};
