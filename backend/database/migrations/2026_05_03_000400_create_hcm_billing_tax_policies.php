<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hcm_billing_tax_policies')) {
            return;
        }

        Schema::create('hcm_billing_tax_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('company_id');
            $table->string('billing_month', 7);
            $table->string('billing_cycle_type', 16);
            $table->decimal('tax_rate_percentage', 5, 2);
            $table->string('base_calculation_method', 64)->default('invoice_amount_due');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 16)->default('active');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'billing_month'], 'hcm_billing_tax_policy_company_month_idx');
            $table->index(['status', 'billing_month'], 'hcm_billing_tax_policy_status_month_idx');
            $table->index(['company_id', 'status'], 'hcm_billing_tax_policy_company_status_idx');
            $table->unique(['company_id', 'billing_month', 'billing_cycle_type'], 'hcm_billing_tax_policy_company_month_cycle_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_billing_tax_policies');
    }
};
