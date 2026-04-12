<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_trainers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('email', 200)->nullable()->index();
            $table->string('phone', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_trainers');
    }
};

