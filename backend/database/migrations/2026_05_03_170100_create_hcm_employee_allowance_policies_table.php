<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hcm_employee_allowance_policies')) {
            return;
        }

        Schema::create('hcm_employee_allowance_policies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('company_id');
            $table->uuid('company_uuid')->nullable();
            $table->string('code', 64);
            $table->string('name', 160);
            $table->string('allowance_type', 32)->default('fixed');
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_mandatory')->default(true);
            $table->decimal('default_amount', 15, 2)->default(0);
            $table->string('frequency', 16)->default('monthly');
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

            $table->index(['company_id', 'is_active', 'effective_start_date'], 'hcm_allowance_policy_active_idx');
            $table->unique(['company_id', 'code', 'effective_start_date'], 'hcm_allowance_policy_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_employee_allowance_policies');
    }
};
