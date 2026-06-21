<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('department', 150)->nullable();
            $table->string('designation_from', 150)->nullable();
            $table->string('designation_to', 150)->nullable();
            $table->date('promotion_date')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'promotion_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_promotions');
    }
};
