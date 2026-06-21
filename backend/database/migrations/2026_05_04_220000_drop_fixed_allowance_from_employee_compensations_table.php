<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('employee_profiles')->update([
            'fixed_allowance' => 0,
        ]);

        if (Schema::hasColumn('employee_compensations', 'fixed_allowance')) {
            DB::table('employee_compensations')->update([
                'fixed_allowance' => 0,
            ]);

            Schema::table('employee_compensations', function (Blueprint $table): void {
                $table->dropColumn('fixed_allowance');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('employee_compensations', 'fixed_allowance')) {
            Schema::table('employee_compensations', function (Blueprint $table): void {
                $table->decimal('fixed_allowance', 15, 2)->default(0)->after('base_salary');
            });
        }
    }
};
