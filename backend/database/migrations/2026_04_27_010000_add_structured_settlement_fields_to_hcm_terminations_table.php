<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_terminations', function (Blueprint $table): void {
            $table->unsignedBigInteger('settlement_payroll_period_id')->nullable()->after('settlement_payroll_period');
            $table->json('settlement_breakdown')->nullable()->after('clearance_notes');
            $table->json('clearance_items')->nullable()->after('settlement_breakdown');
            $table->index('settlement_payroll_period_id', 'hcm_terminations_settlement_period_idx');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_terminations', function (Blueprint $table): void {
            $table->dropIndex('hcm_terminations_settlement_period_idx');
            $table->dropColumn([
                'settlement_payroll_period_id',
                'settlement_breakdown',
                'clearance_items',
            ]);
        });
    }
};