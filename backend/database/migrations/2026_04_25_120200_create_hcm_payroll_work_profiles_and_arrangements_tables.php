<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_payroll_work_profiles')) {
            Schema::create('hcm_payroll_work_profiles', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('code', 80);
                $table->string('name', 120);
                $table->string('arrangement_mode', 30)->default('office_hour');
                $table->string('default_day_type', 40)->default('workday');
                $table->unsignedTinyInteger('weekly_work_days')->default(5);
                $table->boolean('is_default')->default(false);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'hcm_payroll_work_profiles_company_code_unique');
            });
        }

        if (! Schema::hasTable('hcm_employee_work_arrangements')) {
            Schema::create('hcm_employee_work_arrangements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('hcm_payroll_work_profile_id')->nullable();
                $table->string('arrangement_mode', 30)->default('office_hour');
                $table->string('default_day_type', 40)->nullable();
                $table->unsignedTinyInteger('weekly_work_days')->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'effective_from', 'effective_to'], 'hcm_emp_work_arrangements_user_effective_idx');
                $table->index(['company_id', 'arrangement_mode'], 'hcm_emp_work_arrangements_company_mode_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hcm_employee_work_arrangements')) {
            Schema::drop('hcm_employee_work_arrangements');
        }

        if (Schema::hasTable('hcm_payroll_work_profiles')) {
            Schema::drop('hcm_payroll_work_profiles');
        }
    }
};
