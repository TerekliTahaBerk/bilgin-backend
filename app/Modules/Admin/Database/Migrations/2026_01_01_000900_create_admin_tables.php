<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         | Panel kullanıcıları öğrencilerden AYRI tabloda.
         |
         | Tek tabloda "is_admin" bayrağı tutmak, bir yetki hatasında öğrenci
         | hesabının panele düşmesi demektir. Ayrı tablo + ayrı guard, bu
         | sınıf hatayı yapısal olarak imkânsız kılar.
         */
        Schema::create('admin_users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 32);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        /*
         | Denetim kaydı. Çok yazarlı bir CMS'te "bu soruyu kim değiştirdi"
         | sorusunun cevabı olmadan içerik kalitesi yönetilemez.
         */
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('action', 64);
            $table->string('entity_type', 64);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['entity_type', 'entity_id']);
            $table->index(['admin_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('admin_users');
    }
};
