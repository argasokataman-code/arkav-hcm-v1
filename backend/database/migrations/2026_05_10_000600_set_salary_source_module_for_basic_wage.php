<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\HcmSalaryComponent;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('hcm_salary_components')
            ->where('code', HcmSalaryComponent::CODE_BASIC_WAGE)
            ->update(['source_module' => 'salary']);
    }

    public function down(): void
    {
        DB::table('hcm_salary_components')
            ->where('code', HcmSalaryComponent::CODE_BASIC_WAGE)
            ->update(['source_module' => null]);
    }
};
