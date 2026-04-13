<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addCompanyIdColumn('hcm_payroll_items');
        $this->addCompanyIdColumn('hcm_thr_yearly_settings');
        $this->addCompanyIdColumn('hcm_thr_batches');

        $this->backfillDefaultCompany('hcm_payroll_items');
        $this->backfillDefaultCompany('hcm_thr_yearly_settings');
        $this->backfillDefaultCompany('hcm_thr_batches');

        Schema::table('hcm_thr_yearly_settings', function (Blueprint $table): void {
            $table->dropUnique('hcm_thr_yearly_settings_calendar_year_unique');
            $table->unique(['company_id', 'calendar_year'], 'hcm_thr_yearly_settings_company_year_unique');
        });

        Schema::table('hcm_thr_batches', function (Blueprint $table): void {
            $table->index(['company_id', 'calendar_year', 'status'], 'hcm_thr_batches_company_year_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_thr_batches', function (Blueprint $table): void {
            $table->dropIndex('hcm_thr_batches_company_year_status_index');
        });

        Schema::table('hcm_thr_yearly_settings', function (Blueprint $table): void {
            $table->dropUnique('hcm_thr_yearly_settings_company_year_unique');
            $table->unique(['calendar_year']);
        });

        foreach (array_reverse(['hcm_thr_batches', 'hcm_thr_yearly_settings', 'hcm_payroll_items']) as $table) {
            $this->dropCompanyIdColumn($table);
        }
    }

    private function addCompanyIdColumn(string $table): void
    {
        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->unsignedBigInteger('company_id')->nullable()->after('id')->index();
        });
    }

    private function dropCompanyIdColumn(string $table): void
    {
        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropIndex(['company_id']);
            $blueprint->dropColumn('company_id');
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
