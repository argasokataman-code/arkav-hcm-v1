<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah_regencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('province_id')->constrained('wilayah_provinces')->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name', 255);
            $table->timestamps();

            $table->index(['province_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayah_regencies');
    }
};