<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addCompanyIdColumn('attendance_records');
        $this->addCompanyIdColumn('hcm_shifts');
        $this->addCompanyIdColumn('hcm_schedule_timings');

        $this->backfillDefaultCompany('attendance_records');
        $this->backfillDefaultCompany('hcm_shifts');
        $this->backfillDefaultCompany('hcm_schedule_timings');
    }

    public function down(): void
    {
        $this->dropCompanyIdColumn('hcm_schedule_timings');
        $this->dropCompanyIdColumn('hcm_shifts');
        $this->dropCompanyIdColumn('attendance_records');
    }

    private function addCompanyIdColumn(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'company_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->unsignedBigInteger('company_id')->nullable()->index();
        });
    }

    private function dropCompanyIdColumn(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropColumn('company_id');
        });
    }

    private function backfillDefaultCompany(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id') || ! Schema::hasTable('companies')) {
            return;
        }

        $companyId = DB::table('companies')->where('code', 'default_company')->value('id');
        if (! $companyId) {
            $companyId = DB::table('companies')->orderBy('id')->value('id');
        }

        if (! $companyId) {
            return;
        }

        DB::table($table)->whereNull('company_id')->update(['company_id' => $companyId]);
    }
};
