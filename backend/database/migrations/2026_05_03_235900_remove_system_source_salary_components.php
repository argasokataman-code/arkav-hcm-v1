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

        DB::table('hcm_salary_components')
            ->where('source_module', HcmSalaryComponent::SOURCE_MODULE_SYSTEM)
            ->delete();
    }

    public function down(): void
    {
        // No-op. Removed system-sourced rows are intentionally not restored.
    }
};
