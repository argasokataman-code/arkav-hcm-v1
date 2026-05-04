<?php

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

        DB::table('hcm_salary_components')->update([
            'description' => null,
            'legal_basis' => null,
            'legal_notes' => null,
            'default_percent' => null,
            'percent_basis' => null,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No-op. Cleared hardcoded metadata is intentionally not restored.
    }
};
