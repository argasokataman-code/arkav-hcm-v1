<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Level 3: Add per-tenant billing tax snapshot columns to platform_monthly_financial_summaries.
     *
     * When CloseMonthlyFinancialReportJob locks a billing month, it also snapshots the per-tenant
     * tax breakdown into `tenant_billing_snapshots`. From that point on, BillingTaxCalculationService
     * returns the frozen snapshot instead of live-computing from mutable policy configs — guaranteeing
     * that historical recap numbers are immutable after close-of-month.
     */
    public function up(): void
    {
        if (! Schema::hasTable('platform_monthly_financial_summaries')) {
            return;
        }

        Schema::table('platform_monthly_financial_summaries', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_monthly_financial_summaries', 'tenant_billing_snapshots')) {
                // JSON blob of the full generateCrossTenantMonthlyReport() output at lock time.
                $table->json('tenant_billing_snapshots')->nullable()->after('missing_tax_codes');
            }

            if (! Schema::hasColumn('platform_monthly_financial_summaries', 'tax_snapshots_locked_at')) {
                $table->timestamp('tax_snapshots_locked_at')->nullable()->after('tenant_billing_snapshots');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_monthly_financial_summaries')) {
            return;
        }

        Schema::table('platform_monthly_financial_summaries', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_monthly_financial_summaries', 'tax_snapshots_locked_at')) {
                $table->dropColumn('tax_snapshots_locked_at');
            }

            if (Schema::hasColumn('platform_monthly_financial_summaries', 'tenant_billing_snapshots')) {
                $table->dropColumn('tenant_billing_snapshots');
            }
        });
    }
};
