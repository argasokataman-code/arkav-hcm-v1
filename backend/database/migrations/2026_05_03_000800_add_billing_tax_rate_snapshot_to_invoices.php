<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add billing_tax_rate_snapshot to invoices table for AN-013 (snapshot mismatch fix).
        // Stores the tax rate percentage at time of invoice creation so historical recalculations
        // always use the rate that was active when the invoice was issued, not the current policy.
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'billing_tax_rate_snapshot')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->decimal('billing_tax_rate_snapshot', 5, 2)->nullable()->after('amount_due');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'billing_tax_rate_snapshot')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropColumn('billing_tax_rate_snapshot');
            });
        }
    }
};
