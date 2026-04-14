<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah_villages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('district_id')->constrained('wilayah_districts')->cascadeOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 255);
            $table->timestamps();

            $table->index(['district_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayah_villages');
    }
};