<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_resignations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('department', 150)->nullable();
            $table->text('reason');
            $table->date('notice_date')->index();
            $table->date('resignation_date')->index();
            $table->string('status', 32)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'resignation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_resignations');
    }
};
