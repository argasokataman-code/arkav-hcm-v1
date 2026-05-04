<?php

use App\Models\HcmSalaryComponent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_salary_components') || ! Schema::hasColumn('hcm_salary_components', 'source_module')) {
            return;
        }

        $updates = [
            'source_module' => HcmSalaryComponent::SOURCE_MODULE_SYSTEM,
            'company_id' => null,
        ];

        if (Schema::hasColumn('hcm_salary_components', 'company_uuid')) {
            $updates['company_uuid'] = null;
        }

        DB::table('hcm_salary_components')
            ->where('code', HcmSalaryComponent::CODE_BASIC_WAGE)
            ->update($updates);
    }

    public function down(): void
    {
        DB::table('hcm_salary_components')
            ->where('code', HcmSalaryComponent::CODE_BASIC_WAGE)
            ->update([
                'source_module' => HcmSalaryComponent::SOURCE_MODULE_ALLOWANCE,
            ]);
    }
};
