<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // hcm_payroll_periods needs special handling: the existing unique(period_year, period_month)
        // must be widened to (company_id, period_year, period_month) for multi-tenancy.
        Schema::table('hcm_payroll_periods', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable()->after('id')->index();
        });
        $this->backfillDefaultCompany('hcm_payroll_periods');
        Schema::table('hcm_payroll_periods', function (Blueprint $table): void {
            $table->dropUnique('hcm_payroll_periods_period_year_period_month_unique');
            $table->unique(['company_id', 'period_year', 'period_month'], 'hcm_payroll_periods_company_year_month_unique');
        });

        // Remaining tables: plain company_id column + index
        foreach (['hcm_payroll_runs', 'hcm_payroll_lines', 'hcm_salary_components', 'hcm_overtime_types'] as $tbl) {
            $this->addCompanyIdColumn($tbl);
            $this->backfillDefaultCompany($tbl);
        }
    }

    public function down(): void
    {
        // Restore hcm_payroll_periods constraint
        Schema::table('hcm_payroll_periods', function (Blueprint $table): void {
            $table->dropUnique('hcm_payroll_periods_company_year_month_unique');
            $table->unique(['period_year', 'period_month']);
            $table->dropIndex(['company_id']);
            $table->dropColumn('company_id');
        });

        foreach (array_reverse(['hcm_payroll_runs', 'hcm_payroll_lines', 'hcm_salary_components', 'hcm_overtime_types']) as $tbl) {
            $this->dropCompanyIdColumn($tbl);
        }
    }

    private function addCompanyIdColumn(string $table): void
    {
        Schema::table($table, function (Blueprint $t): void {
            $t->unsignedBigInteger('company_id')->nullable()->after('id')->index();
        });
    }

    private function dropCompanyIdColumn(string $table): void
    {
        Schema::table($table, function (Blueprint $t): void {
            $t->dropIndex(['company_id']);
            $t->dropColumn('company_id');
        });
    }

    private function backfillDefaultCompany(string $table): void
    {
        $company = DB::table('companies')->where('code', 'default_company')->first();
        if ($company === null) {
            $company = DB::table('companies')->orderBy('id')->first();
        }
        if ($company === null) {
            return;
        }
        DB::table($table)->whereNull('company_id')->update(['company_id' => $company->id]);
    }
};
