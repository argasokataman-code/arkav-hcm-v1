<?php

use App\Models\HcmSalaryComponent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_salary_components')) {
            return;
        }

        // upah_pokok bukan milik module governance manapun (bpjs/allowance/thr/dll)
        // source_module=null = komponen inti payroll tenant-managed, tapi is_system_locked=true = tidak bisa dihapus
        DB::table('hcm_salary_components')
            ->where('code', HcmSalaryComponent::CODE_BASIC_WAGE)
            ->update(['source_module' => null]);
    }

    public function down(): void
    {
        DB::table('hcm_salary_components')
            ->where('code', HcmSalaryComponent::CODE_BASIC_WAGE)
            ->update(['source_module' => HcmSalaryComponent::SOURCE_MODULE_SYSTEM]);
    }
};
