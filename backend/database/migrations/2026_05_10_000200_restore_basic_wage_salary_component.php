<?php

use App\Models\HcmSalaryComponent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_salary_components')) {
            return;
        }

        $now = now();

        $payload = [
            'name' => 'Upah / Gaji Pokok',
            'kind' => 'addition',
            'category' => 'basic_wage',
            'include_bpjs_health_wage_base' => true,
            'include_bpjs_tk_wage_base' => true,
            'include_thr_calculation_base' => true,
            'include_pph21_ter_gross' => true,
            'include_pph21_annual_reconciliation' => true,
            'subject_overtime_regulation' => false,
            'affects_net_pay' => true,
            'employer_cost_line' => false,
            'is_system_locked' => true,
            'source_module' => HcmSalaryComponent::SOURCE_MODULE_SYSTEM,
            'sort_order' => 10,
            'is_active' => true,
            'company_id' => null,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('hcm_salary_components', 'company_uuid')) {
            $payload['company_uuid'] = null;
        }
        if (Schema::hasColumn('hcm_salary_components', 'uuid')) {
            $payload['uuid'] = (string) Str::uuid();
        }

        if (Schema::hasColumn('hcm_salary_components', 'category_uuid')) {
            $categoryUuid = DB::table('hcm_salary_component_categories')
                ->where('kind', 'addition')
                ->where('code', 'basic_wage')
                ->value('uuid');
            if ($categoryUuid) {
                $payload['category_uuid'] = $categoryUuid;
            }
        }

        $existing = DB::table('hcm_salary_components')->where('code', HcmSalaryComponent::CODE_BASIC_WAGE)->first();
        if ($existing) {
            if (Schema::hasColumn('hcm_salary_components', 'uuid') && ! isset($existing->uuid)) {
                $payload['uuid'] = (string) Str::uuid();
            } else {
                unset($payload['uuid']);
            }

            DB::table('hcm_salary_components')
                ->where('id', $existing->id)
                ->update($payload);

            return;
        }

        $insert = $payload;
        $insert['code'] = HcmSalaryComponent::CODE_BASIC_WAGE;
        $insert['created_at'] = $now;
        DB::table('hcm_salary_components')->insert($insert);
    }

    public function down(): void
    {
        DB::table('hcm_salary_components')
            ->where('code', HcmSalaryComponent::CODE_BASIC_WAGE)
            ->delete();
    }
};
