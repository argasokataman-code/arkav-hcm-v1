<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('hcm_salary_components')) {
            return;
        }

        // Keep the canonical unique index and drop the redundant duplicate.
        if (Schema::hasIndex('hcm_salary_components', 'unique_salary_component_code')) {
            Schema::table('hcm_salary_components', function ($table): void {
                $table->dropUnique('unique_salary_component_code');
            });
        }

        if (! Schema::hasIndex('hcm_salary_components', ['code'], 'unique')) {
            Schema::table('hcm_salary_components', function ($table): void {
                $table->unique('code', 'hcm_salary_components_code_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('hcm_salary_components')) {
            return;
        }

        if (! Schema::hasIndex('hcm_salary_components', 'unique_salary_component_code')) {
            Schema::table('hcm_salary_components', function ($table): void {
                $table->unique('code', 'unique_salary_component_code');
            });
        }
    }
};
