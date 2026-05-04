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
        if (! Schema::hasTable('hcm_salary_components') || ! Schema::hasColumn('hcm_salary_components', 'uuid')) {
            return;
        }

        DB::table('hcm_salary_components')
            ->where('code', HcmSalaryComponent::CODE_BASIC_WAGE)
            ->where(function ($query): void {
                $query->whereNull('uuid')->orWhere('uuid', '');
            })
            ->update([
                'uuid' => (string) Str::uuid(),
            ]);
    }

    public function down(): void
    {
        // No-op. UUID should remain stable once generated.
    }
};
