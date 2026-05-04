<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('employee_profiles')->update([
            'fixed_allowance' => 0,
        ]);
    }

    public function down(): void
    {
        // Irreversible normalization: historical fixed allowance values are intentionally removed.
    }
};
