<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_profiles') && Schema::hasColumn('employee_profiles', 'contract_type')) {
            DB::table('employee_profiles')
                ->whereNotNull('contract_type')
                ->update([
                    'contract_type' => DB::raw("CASE WHEN LOWER(TRIM(contract_type)) IN ('pkwt', 'contract') THEN 'contract' ELSE 'permanent' END"),
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('employee_contracts') && Schema::hasColumn('employee_contracts', 'contract_type')) {
            DB::table('employee_contracts')
                ->whereNotNull('contract_type')
                ->update([
                    'contract_type' => DB::raw("CASE WHEN LOWER(TRIM(contract_type)) IN ('pkwt', 'contract') THEN 'contract' ELSE 'permanent' END"),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_profiles') && Schema::hasColumn('employee_profiles', 'contract_type')) {
            DB::table('employee_profiles')
                ->whereIn('contract_type', ['contract', 'permanent'])
                ->update([
                    'contract_type' => DB::raw("CASE WHEN contract_type = 'contract' THEN 'pkwt' ELSE 'pkwtt' END"),
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('employee_contracts') && Schema::hasColumn('employee_contracts', 'contract_type')) {
            DB::table('employee_contracts')
                ->whereIn('contract_type', ['contract', 'permanent'])
                ->update([
                    'contract_type' => DB::raw("CASE WHEN contract_type = 'contract' THEN 'pkwt' ELSE 'pkwtt' END"),
                    'updated_at' => now(),
                ]);
        }
    }
};
