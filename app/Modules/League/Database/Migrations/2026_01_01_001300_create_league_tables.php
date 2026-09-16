<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table): void {
            $table->id();
            $table->string('tier', 16);                    // bronze … champion
            $table->date('week_start');                    // Pazartesi (Europe/Istanbul)
            $table->unsignedSmallInteger('capacity')->default(30);
            $table->unsignedSmallInteger('member_count')->default(0);
            $table->string('status', 16)->default('active');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            // Aynı hafta aynı kademede birden fazla kohort olur; doldurma
            // sırasında en eski dolmamış olan seçilir.
            $table->index(['tier', 'week_start', 'status']);
        });

        Schema::create('league_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('league_id')->constrained('leagues')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('weekly_xp')->default(0);
            $table->unsignedSmallInteger('final_rank')->nullable();
            $table->string('result', 16)->nullable();      // promoted | demoted | stayed
            $table->date('week_start');
            $table->timestamps();

            $table->unique(['league_id', 'user_id']);
            // Bir kullanıcı bir haftada yalnızca bir ligde olabilir.
            $table->unique(['user_id', 'week_start']);
            // Sıralama sorgusu: kohortun üyeleri XP'ye göre.
            $table->index(['league_id', 'weekly_xp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_memberships');
        Schema::dropIfExists('leagues');
    }
};
