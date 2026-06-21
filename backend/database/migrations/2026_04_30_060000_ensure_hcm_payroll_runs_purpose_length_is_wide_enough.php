<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_payroll_runs') || ! Schema::hasColumn('hcm_payroll_runs', 'purpose')) {
            return;
        }

        Schema::table('hcm_payroll_runs', function (Blueprint $table): void {
            $table->string('purpose', 32)->default('monthly')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hcm_payroll_runs') || ! Schema::hasColumn('hcm_payroll_runs', 'purpose')) {
            return;
        }

        Schema::table('hcm_payroll_runs', function (Blueprint $table): void {
            $table->string('purpose', 16)->default('monthly')->change();
        });
    }
};
