<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Dashboard Metrics table (cached metrics)
        Schema::create('dashboard_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date')->index();
            $table->string('metric_key')->index();
            $table->float('metric_value');
            $table->json('metric_metadata')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('next_calculation_at')->nullable();
            $table->timestamps();

            // Composite unique index for metric uniqueness per date
            $table->unique(['metric_date', 'metric_key']);
            $table->index('next_calculation_at');
        });

        // Audit Logs table
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('super_admin_id');
            $table->string('action');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('super_admin_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index('super_admin_id');
            $table->index('action');
            $table->index('target_type');
            $table->index('created_at');
            $table->index(['target_type', 'target_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('dashboard_metrics');
    }
};
