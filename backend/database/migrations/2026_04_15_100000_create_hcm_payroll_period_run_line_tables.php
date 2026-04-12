<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('status', 24)->default('open');
            $table->timestamps();

            $table->unique(['period_year', 'period_month']);
        });

        Schema::create('hcm_payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hcm_payroll_period_id')->constrained('hcm_payroll_periods')->cascadeOnDelete();
            $table->string('status', 24)->default('draft');
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hcm_payroll_period_id', 'status']);
        });

        Schema::create('hcm_payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hcm_payroll_run_id')->constrained('hcm_payroll_runs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hcm_salary_component_id')->nullable()->constrained('hcm_salary_components')->nullOnDelete();
            $table->string('component_code', 64)->nullable();
            $table->string('component_name', 200)->nullable();
            $table->string('kind', 32);
            $table->string('category', 64)->nullable();
            $table->decimal('amount', 15, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['hcm_payroll_run_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_payroll_lines');
        Schema::dropIfExists('hcm_payroll_runs');
        Schema::dropIfExists('hcm_payroll_periods');
    }
};
