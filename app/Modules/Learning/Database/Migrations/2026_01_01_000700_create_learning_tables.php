<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bir "tur". Tasarımdaki çalışma ekranının ömrü bu kayıttır.
        Schema::create('study_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unit_node_id')->constrained('unit_nodes')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('status', 16)->default('active');
            $table->boolean('consumes_hearts')->default(true);
            $table->unsignedSmallInteger('time_limit_sec')->nullable();
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('wrong_count')->default(0);
            $table->unsignedTinyInteger('accuracy')->default(0);
            $table->unsignedSmallInteger('xp_awarded')->default(0);
            $table->boolean('is_perfect')->default(false);
            $table->boolean('is_replay')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            // Ağ hatasında tekrar gönderilen "oturum başlat" ikinci tur açmaz.
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'status', 'started_at']);
        });

        /*
         | Oturuma seçilen sorular — ANLIK GÖRÜNTÜ ile.
         |
         | Snapshot zorunlu: node'lar sabit liste değil selection_rule tutar,
         | yani aynı node her açılışta farklı soru getirebilir. İçerik ekibi
         | araya düzeltme yapsa bile devam eden oturum bozulmaz.
         */
        Schema::create('session_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('study_session_id')->constrained('study_sessions')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('type', 32);
            $table->unsignedInteger('exercise_version');
            $table->jsonb('content_snapshot');
            $table->jsonb('answer_key_snapshot');
            $table->text('explanation_snapshot')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->decimal('partial_score', 4, 3)->nullable();
            $table->unsignedInteger('elapsed_ms')->nullable();
            $table->boolean('is_suspicious')->default(false);
            $table->timestamps();

            $table->unique(['study_session_id', 'position']);
            $table->unique(['study_session_id', 'exercise_id']);
        });

        // Analiz için append-only. Zayıf/güçlü konu ve adaptif zorluk buradan beslenir.
        Schema::create('answer_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->foreignId('study_session_id')->constrained('study_sessions')->cascadeOnDelete();
            $table->boolean('is_correct');
            $table->decimal('partial_score', 4, 3);
            $table->unsignedInteger('elapsed_ms');
            $table->boolean('is_suspicious')->default(false);
            $table->timestamp('answered_at');

            $table->index(['user_id', 'topic_id', 'answered_at']);
            $table->index(['user_id', 'answered_at']);
        });

        Schema::create('user_node_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unit_node_id')->constrained('unit_nodes')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->boolean('completed')->default(false);
            $table->unsignedTinyInteger('best_accuracy')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->boolean('is_perfect')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'unit_node_id']);
            $table->index(['user_id', 'course_id']);
        });

        Schema::create('user_unit_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedTinyInteger('completion_percent')->default(0);
            $table->unsignedSmallInteger('completed_nodes')->default(0);
            $table->unsignedSmallInteger('total_nodes')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'unit_id']);
        });

        /*
         | Ders ilerlemesi. exam_variant_id YOK — ilerleme derse aittir,
         | öğrencinin alanına değil. Alan değişimi bu tabloya dokunmaz.
         */
        Schema::create('user_course_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedSmallInteger('completed_units')->default(0);
            $table->unsignedSmallInteger('total_units')->default(0);
            $table->timestamp('last_studied_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });

        // topic_id subject seviyesinde → TYT ve AYT ustalığı BİRLEŞİK sayılır.
        Schema::create('user_topic_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('correct')->default(0);
            $table->unsignedTinyInteger('accuracy')->default(0);
            $table->string('mastery', 16)->default('unknown');
            $table->timestamp('last_practiced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'topic_id']);
            $table->index(['user_id', 'mastery']);
        });

        // "Hızlı Tekrar" node'unun kaynağı. Faz 2'de SM-2 aralıkları eklenir.
        Schema::create('review_queue', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->timestamp('due_at');
            $table->unsignedSmallInteger('interval_days')->default(3);
            $table->unsignedSmallInteger('lapses')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'exercise_id']);
            $table->index(['user_id', 'unit_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_queue');
        Schema::dropIfExists('user_topic_stats');
        Schema::dropIfExists('user_course_progress');
        Schema::dropIfExists('user_unit_progress');
        Schema::dropIfExists('user_node_progress');
        Schema::dropIfExists('answer_attempts');
        Schema::dropIfExists('session_items');
        Schema::dropIfExists('study_sessions');
    }
};
