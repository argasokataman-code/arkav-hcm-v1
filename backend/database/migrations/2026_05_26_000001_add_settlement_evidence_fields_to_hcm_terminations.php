<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slice A — Settlement Evidence Snapshot + Leave Balance Availability Flag
 *
 * Stores an immutable snapshot of the data used during settlement calculation
 * (hire_date, base_salary, leave_balance, formula version) so pre-finalization
 * drift detection can compare against current employee state and raise an error
 * if critical fields changed after calculation (Anomaly #1 mitigation).
 *
 * leave_balance_available = false means leave service was unavailable during
 * calculation; finalization must be explicitly confirmed by admin (Anomaly #4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_terminations', function (Blueprint $table): void {
            // Evidence snapshot: fields used during settlement calculation
            $table->json('settlement_evidence_snapshot')->nullable()->after('settlement_breakdown');

            // Explicit flag: NULL = not yet calculated, false = service unavailable, true = balance fetched
            $table->boolean('leave_balance_available')->nullable()->after('settlement_evidence_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('hcm_terminations', function (Blueprint $table): void {
            $table->dropColumn(['settlement_evidence_snapshot', 'leave_balance_available']);
        });
    }
};
