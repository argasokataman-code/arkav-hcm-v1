<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_goal_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['name']);
            $table->index(['is_active']);
        });

        Schema::create('performance_goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goal_type_id')->nullable()->constrained('performance_goal_types')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('subject', 200);
            $table->string('target_achievement', 255)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();

            $table->enum('status', ['active', 'inactive', 'completed'])->default('active');
            $table->unsignedTinyInteger('progress_percent')->default(0); // 0..100

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['manager_user_id', 'status']);
            $table->index(['goal_type_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_goals');
        Schema::dropIfExists('performance_goal_types');
    }
};

