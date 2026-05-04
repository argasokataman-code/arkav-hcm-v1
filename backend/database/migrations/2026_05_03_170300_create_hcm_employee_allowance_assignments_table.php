<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hcm_employee_allowance_assignments')) {
            return;
        }

        Schema::create('hcm_employee_allowance_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id');
            $table->uuid('company_uuid')->nullable();
            $table->unsignedBigInteger('policy_id');
            $table->uuid('policy_uuid')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->uuid('user_uuid')->nullable();
            $table->decimal('amount_override', 15, 2)->nullable();
            $table->date('effective_start_date');
            $table->date('effective_end_date')->nullable();
            $table->string('status', 32)->default('active');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->uuid('created_by_user_uuid')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->uuid('updated_by_user_uuid')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id'], 'hcm_allowance_assignment_company_user_idx');
            $table->index(['company_id', 'policy_id', 'is_active'], 'hcm_allowance_assignment_policy_active_idx');
            $table->index(['company_id', 'effective_start_date', 'effective_end_date'], 'hcm_allowance_assignment_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_employee_allowance_assignments');
    }
};
