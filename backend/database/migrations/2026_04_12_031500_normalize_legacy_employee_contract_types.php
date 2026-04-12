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
                ->whereRaw('LOWER(TRIM(contract_type)) in (?, ?, ?, ?)', ['permanent', 'pkwtt', 'pkwt', 'contract'])
                ->update([
                    'contract_type' => DB::raw("CASE WHEN contract_end_date IS NOT NULL THEN 'contract' ELSE 'permanent' END"),
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('employee_contracts') && Schema::hasColumn('employee_contracts', 'contract_type')) {
            DB::table('employee_contracts')
                ->whereRaw('LOWER(TRIM(contract_type)) in (?, ?, ?, ?)', ['permanent', 'pkwtt', 'pkwt', 'contract'])
                ->update([
                    'contract_type' => DB::raw("CASE WHEN end_date IS NOT NULL THEN 'contract' ELSE 'permanent' END"),
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
                    'contract_type' => DB::raw("CASE WHEN contract_end_date IS NOT NULL THEN 'pkwt' ELSE 'pkwtt' END"),
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('employee_contracts') && Schema::hasColumn('employee_contracts', 'contract_type')) {
            DB::table('employee_contracts')
                ->whereIn('contract_type', ['contract', 'permanent'])
                ->update([
                    'contract_type' => DB::raw("CASE WHEN end_date IS NOT NULL THEN 'pkwt' ELSE 'pkwtt' END"),
                    'updated_at' => now(),
                ]);
        }
    }
};
