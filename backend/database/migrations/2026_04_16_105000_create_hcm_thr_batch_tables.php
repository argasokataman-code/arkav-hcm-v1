<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hcm_thr_batches')) {
            return;
        }

        Schema::create('hcm_thr_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('calendar_year')->index();
            $table->foreignId('hcm_thr_yearly_setting_id')->nullable()->constrained('hcm_thr_yearly_settings')->nullOnDelete();
            $table->date('cutoff_date');
            $table->decimal('grand_total_eligible', 15, 2)->default(0);
            $table->unsignedInteger('eligible_line_count')->default(0);
            $table->unsignedInteger('total_line_count')->default(0);
            $table->string('status', 24)->default('draft');
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('hcm_payroll_period_id')->nullable()->constrained('hcm_payroll_periods')->nullOnDelete();
            $table->foreignId('hcm_payroll_run_id')->nullable()->constrained('hcm_payroll_runs')->nullOnDelete();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['calendar_year', 'status']);
        });

        Schema::create('hcm_thr_batch_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hcm_thr_batch_id')->constrained('hcm_thr_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('full_name', 200);
            $table->string('employee_no', 32)->nullable();
            $table->date('join_date_used');
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('fixed_allowance', 15, 2)->default(0);
            $table->decimal('reference_wage', 15, 2)->default(0);
            $table->unsignedSmallInteger('months_of_service')->default(0);
            $table->decimal('multiplier', 12, 6)->default(0);
            $table->decimal('thr_gross', 15, 2)->default(0);
            $table->string('row_status', 24);
            $table->boolean('eligible')->default(false);
            $table->timestamps();

            $table->unique(['hcm_thr_batch_id', 'user_id']);
            $table->index(['hcm_thr_batch_id', 'eligible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_thr_batch_lines');
        Schema::dropIfExists('hcm_thr_batches');
    }
};
