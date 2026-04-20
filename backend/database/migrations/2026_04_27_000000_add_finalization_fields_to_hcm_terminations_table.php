<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_terminations')) {
            return;
        }

        Schema::table('hcm_terminations', function (Blueprint $table): void {
            if (! Schema::hasColumn('hcm_terminations', 'settlement_payroll_period')) {
                $table->string('settlement_payroll_period', 7)->nullable()->after('notes');
            }
            if (! Schema::hasColumn('hcm_terminations', 'final_salary_amount')) {
                $table->decimal('final_salary_amount', total: 15, places: 2)->nullable()->after('settlement_payroll_period');
            }
            if (! Schema::hasColumn('hcm_terminations', 'final_allowance_amount')) {
                $table->decimal('final_allowance_amount', total: 15, places: 2)->nullable()->after('final_salary_amount');
            }
            if (! Schema::hasColumn('hcm_terminations', 'final_deduction_amount')) {
                $table->decimal('final_deduction_amount', total: 15, places: 2)->nullable()->after('final_allowance_amount');
            }
            if (! Schema::hasColumn('hcm_terminations', 'asset_return_notes')) {
                $table->text('asset_return_notes')->nullable()->after('final_deduction_amount');
            }
            if (! Schema::hasColumn('hcm_terminations', 'clearance_notes')) {
                $table->text('clearance_notes')->nullable()->after('asset_return_notes');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hcm_terminations')) {
            return;
        }

        Schema::table('hcm_terminations', function (Blueprint $table): void {
            foreach (['clearance_notes', 'asset_return_notes', 'final_deduction_amount', 'final_allowance_amount', 'final_salary_amount', 'settlement_payroll_period'] as $column) {
                if (Schema::hasColumn('hcm_terminations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};