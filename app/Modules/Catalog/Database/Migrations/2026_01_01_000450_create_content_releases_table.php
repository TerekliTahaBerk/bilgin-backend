<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Mobil istemci manifest sürümünü buradan okur, yalnızca değişen paketi indirir. */
    public function up(): void
    {
        Schema::create('content_releases', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->string('manifest_hash', 64);
            $table->text('changelog')->nullable();
            $table->string('published_by')->nullable();
            $table->timestamp('published_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_releases');
    }
};
