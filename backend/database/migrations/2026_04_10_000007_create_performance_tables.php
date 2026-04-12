<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard for partial DDL on previous failed runs.
        Schema::dropIfExists('performance_review_scores');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('performance_indicator_items');
        Schema::dropIfExists('performance_indicator_templates');
        Schema::dropIfExists('performance_cycles');

        Schema::create('performance_cycles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 200);
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->timestamps();
        });

        Schema::create('performance_indicator_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 200);
            $table->string('department', 120)->nullable();
            $table->string('designation', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Custom name to avoid MySQL 64-char identifier limit.
            $table->index(['department', 'designation', 'is_active'], 'pit_dept_desig_active_idx');
        });

        Schema::create('performance_indicator_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('performance_indicator_templates')->cascadeOnDelete();
            $table->enum('section', ['kpi', 'behavioral']);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->decimal('weight', 8, 2)->nullable(); // for KPI only
            $table->unsignedTinyInteger('rating_scale_min')->default(1);
            $table->unsignedTinyInteger('rating_scale_max')->default(5);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['template_id', 'section', 'sort_order']);
        });

        Schema::create('performance_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cycle_id')->constrained('performance_cycles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('template_id')->constrained('performance_indicator_templates')->restrictOnDelete();
            $table->enum('status', ['draft', 'submitted', 'manager_reviewed', 'finalized'])->default('draft');

            $table->text('self_note')->nullable();
            $table->text('manager_note')->nullable();
            $table->text('final_note')->nullable();

            $table->decimal('self_total_score', 8, 2)->nullable();
            $table->decimal('manager_total_score', 8, 2)->nullable();
            $table->decimal('final_total_score', 8, 2)->nullable();

            $table->timestamps();

            $table->unique(['cycle_id', 'user_id']);
            $table->index(['manager_user_id', 'status']);
            $table->index(['cycle_id', 'status']);
        });

        Schema::create('performance_review_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained('performance_reviews')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('performance_indicator_items')->cascadeOnDelete();

            $table->decimal('self_score', 8, 2)->nullable();
            $table->decimal('manager_score', 8, 2)->nullable();
            $table->decimal('final_score', 8, 2)->nullable();

            $table->text('self_comment')->nullable();
            $table->text('manager_comment')->nullable();
            $table->text('final_comment')->nullable();

            $table->timestamps();

            $table->unique(['review_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_review_scores');
        Schema::dropIfExists('performance_reviews');
        Schema::dropIfExists('performance_indicator_items');
        Schema::dropIfExists('performance_indicator_templates');
        Schema::dropIfExists('performance_cycles');
    }
};
