<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_training_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('hcm_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_type_id')->nullable()->constrained('hcm_training_types')->nullOnDelete();

            $table->string('trainer_name', 200)->nullable();
            $table->date('start_date')->nullable()->index();
            $table->date('end_date')->nullable()->index();
            $table->text('description')->nullable();
            $table->unsignedInteger('cost_cents')->default(0);
            $table->string('status', 24)->default('active')->index(); // active|inactive|completed

            $table->timestamps();
        });

        Schema::create('hcm_training_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('hcm_trainings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['training_id', 'user_id']);
            $table->index(['user_id', 'training_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_training_participants');
        Schema::dropIfExists('hcm_trainings');
        Schema::dropIfExists('hcm_training_types');
    }
};
