<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Misafir giriş birinci sınıf vatandaştır.
     *
     * Tasarımda onboarding "Başla" ile açılıyor; kayıt ekranı yok. Bu yüzden
     * email ve password nullable: kullanıcı önce oynar, hesabı sonra bağlar.
     * Kayıt zorunlu tutulsaydı onboarding'in ilk adımında kullanıcı kaybederdik.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->nullable();          // lig sıralamasında görünen ad
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('avatar_key', 64)->nullable();
            $table->boolean('is_guest')->default(true);
            $table->string('timezone', 64)->default('Europe/Istanbul');
            $table->string('locale', 8)->default('tr');
            $table->unsignedSmallInteger('birth_year')->nullable();
            // LGS segmenti 18 yaş altı: ebeveyn onayı yoksa reklam
            // kişiselleştirmesi kapalı, veri işleme kısıtlı (KVKK).
            $table->timestamp('parental_consent_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
