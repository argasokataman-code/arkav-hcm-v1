<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('hcm_payroll_runs', 'purpose')) {
            Schema::table('hcm_payroll_runs', function (Blueprint $table) {
                $table->string('purpose', 16)->default('monthly')->after('hcm_payroll_period_id');
                $table->index(['hcm_payroll_period_id', 'purpose', 'status'], 'hcm_payroll_runs_period_purpose_status_idx');
            });
        }

        DB::table('hcm_payroll_runs')->whereNull('purpose')->update(['purpose' => 'monthly']);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('hcm_payroll_runs', 'purpose')) {
            return;
        }

        Schema::table('hcm_payroll_runs', function (Blueprint $table) {
            $table->dropIndex('hcm_payroll_runs_period_purpose_status_idx');
            $table->dropColumn('purpose');
        });
    }
};
