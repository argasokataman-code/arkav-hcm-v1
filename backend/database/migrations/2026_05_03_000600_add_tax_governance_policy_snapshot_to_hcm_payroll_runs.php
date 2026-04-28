<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_payroll_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('hcm_payroll_runs', 'hcm_tax_governance_policy_id')) {
                $table->unsignedBigInteger('hcm_tax_governance_policy_id')->nullable()->after('meta');
                $table->index('hcm_tax_governance_policy_id', 'hcm_payroll_runs_tax_policy_id_idx');
            }

            if (! Schema::hasColumn('hcm_payroll_runs', 'hcm_tax_governance_policy_version')) {
                $table->unsignedSmallInteger('hcm_tax_governance_policy_version')->nullable()->after('hcm_tax_governance_policy_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hcm_payroll_runs', function (Blueprint $table): void {
            if (Schema::hasColumn('hcm_payroll_runs', 'hcm_tax_governance_policy_id')) {
                $table->dropIndex('hcm_payroll_runs_tax_policy_id_idx');
                $table->dropColumn('hcm_tax_governance_policy_id');
            }

            if (Schema::hasColumn('hcm_payroll_runs', 'hcm_tax_governance_policy_version')) {
                $table->dropColumn('hcm_tax_governance_policy_version');
            }
        });
    }
};
