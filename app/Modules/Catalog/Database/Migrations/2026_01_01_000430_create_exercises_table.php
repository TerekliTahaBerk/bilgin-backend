<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SORU HAVUZU — soru bir node'un malı değildir, TOPIC'e bağlıdır.
     *
     * Bu sayede aynı soru Çalışma 2'de, Ünite Challenge'da ve Sınav Provasında
     * tek kayıt olarak kullanılır; applicable_scopes ile hem TYT hem AYT
     * havuzuna girebilir.
     *
     * answer_key HİÇBİR KOŞULDA istemciye gitmez — değerlendirme sunucudadır.
     */
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            // Yalnızca yazım/sahiplik içindir — seçim bunlara göre YAPILMAZ.
            $table->foreignId('owner_course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('owner_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('type', 32);
            $table->jsonb('content');
            $table->jsonb('answer_key');
            $table->text('explanation')->nullable();
            $table->unsignedTinyInteger('difficulty')->default(3);   // 1–5
            $table->jsonb('applicable_scopes');                      // ["tyt","ayt"]
            $table->jsonb('media')->nullable();
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        // En sıcak sorgu: her oturum başlangıcında havuzdan seçim yapılır.
        DB::statement('CREATE INDEX exercises_pool_idx ON exercises (topic_id, difficulty, type) WHERE status = \'published\'');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX exercises_scopes_idx ON exercises USING gin (applicable_scopes jsonb_path_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
