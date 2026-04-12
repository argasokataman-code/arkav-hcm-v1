<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            return;
        }

        $hasContractType = Schema::hasColumn('employee_profiles', 'contract_type');
        $hasContractStartDate = Schema::hasColumn('employee_profiles', 'contract_start_date');
        $hasContractEndDate = Schema::hasColumn('employee_profiles', 'contract_end_date');

        if ($hasContractType && $hasContractStartDate && $hasContractEndDate) {
            return;
        }

        Schema::table('employee_profiles', function (Blueprint $table) use ($hasContractType, $hasContractStartDate, $hasContractEndDate) {
            if (! $hasContractType) {
                $table->string('contract_type', 32)->default('permanent')->after('fixed_allowance');
            }
            if (! $hasContractStartDate) {
                $table->date('contract_start_date')->nullable()->after('contract_type');
            }
            if (! $hasContractEndDate) {
                $table->date('contract_end_date')->nullable()->after('contract_start_date');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_profiles')) {
            return;
        }

        Schema::table('employee_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('employee_profiles', 'contract_end_date')) {
                $table->dropColumn('contract_end_date');
            }
            if (Schema::hasColumn('employee_profiles', 'contract_start_date')) {
                $table->dropColumn('contract_start_date');
            }
            if (Schema::hasColumn('employee_profiles', 'contract_type')) {
                $table->dropColumn('contract_type');
            }
        });
    }
};
