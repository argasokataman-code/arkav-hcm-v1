<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Revert hcm_payroll_periods/runs/lines company_id back to nullable.
 *
 * The previous migration (2026_05_14_000100) incorrectly made these three
 * columns NOT NULL. The PayrollDraftBuilder and HcmPayrollPeriodController
 * intentionally support NULL company_id for "shared/global" periods
 * (e.g. a period not owned by any single company, with per-tenant runs).
 *
 * attendance_records and leave_requests remain NOT NULL (always tenant-scoped).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE hcm_payroll_periods MODIFY company_id BIGINT UNSIGNED NULL DEFAULT NULL');
        DB::statement('ALTER TABLE hcm_payroll_runs    MODIFY company_id BIGINT UNSIGNED NULL DEFAULT NULL');
        DB::statement('ALTER TABLE hcm_payroll_lines   MODIFY company_id BIGINT UNSIGNED NULL DEFAULT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Only safe to re-apply NOT NULL if no NULL rows exist.
        foreach (['hcm_payroll_periods', 'hcm_payroll_runs', 'hcm_payroll_lines'] as $table) {
            $row = DB::select("SELECT COUNT(*) AS cnt FROM `{$table}` WHERE company_id IS NULL");
            $count = (int) ($row[0]->cnt ?? 0);
            if ($count > 0) {
                continue; // skip — cannot enforce NOT NULL with existing NULL rows
            }
            DB::statement("ALTER TABLE `{$table}` MODIFY company_id BIGINT UNSIGNED NOT NULL");
        }
    }
};
