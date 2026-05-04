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

        $updates = ['company_id' => null];
        if (Schema::hasColumn('hcm_salary_components', 'company_uuid')) {
            $updates['company_uuid'] = null;
        }

        DB::table('hcm_salary_components')
            ->where('source_module', HcmSalaryComponent::SOURCE_MODULE_ALLOWANCE)
            ->where('is_system_locked', true)
            ->whereNotNull('company_id')
            ->update($updates);
    }

    public function down(): void
    {
        // No-op. Global defaults should remain global once corrected.
    }
};
