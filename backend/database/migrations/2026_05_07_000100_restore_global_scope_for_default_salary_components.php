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

        if (! Schema::hasColumn('hcm_salary_components', 'company_id') || ! Schema::hasColumn('hcm_salary_components', 'source_module')) {
            return;
        }

        $modules = [
            HcmSalaryComponent::SOURCE_MODULE_SYSTEM,
            HcmSalaryComponent::SOURCE_MODULE_BPJS,
            HcmSalaryComponent::SOURCE_MODULE_PPH21,
            HcmSalaryComponent::SOURCE_MODULE_OVERTIME,
            HcmSalaryComponent::SOURCE_MODULE_THR,
            HcmSalaryComponent::SOURCE_MODULE_PKWT,
        ];

        $updates = ['company_id' => null];
        if (Schema::hasColumn('hcm_salary_components', 'company_uuid')) {
            $updates['company_uuid'] = null;
        }

        DB::table('hcm_salary_components')
            ->whereIn('source_module', $modules)
            ->where('is_system_locked', true)
            ->whereNotNull('company_id')
            ->update($updates);
    }

    public function down(): void
    {
        // No-op. Global defaults should remain global once corrected.
    }
};
