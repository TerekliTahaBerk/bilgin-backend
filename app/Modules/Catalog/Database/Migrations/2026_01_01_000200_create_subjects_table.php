<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kavramsal ders: Matematik, Tarih… Sınavdan bağımsız. Konular buraya asılır. */
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('name');
            $table->string('icon', 64)->nullable();
            $table->string('color', 9)->nullable();     // tasarım paleti: #14976B
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
