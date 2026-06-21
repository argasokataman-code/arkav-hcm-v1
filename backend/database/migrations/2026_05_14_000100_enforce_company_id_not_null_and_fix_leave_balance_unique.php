<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit closure migration:
 * 1. Enforce NOT NULL on company_id for 6 strictly tenant-scoped operational tables.
 *    (All currently have 0 NULL rows — safe to tighten constraint.)
 * 2. Fix employee_leave_balances unique index to include company_id to prevent
 *    cross-tenant duplicate balance entries (gap found in schema audit).
 */
return new class extends Migration
{
    /**
     * Tenant-scoped tables that must never have NULL company_id.
     * company_id type is bigint unsigned in all cases.
     */
    private array $tenantTables = [
        'employee_profiles',
        'attendance_records',
        'leave_requests',
        'hcm_payroll_periods',
        'hcm_payroll_runs',
        'hcm_payroll_lines',
    ];

    public function up(): void
    {
        // ── 1. Enforce NOT NULL on company_id ──────────────────────────────────
        foreach ($this->tenantTables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            // Safety: abort if any NULL exists (should not happen, but guard anyway)
            $nullCount = DB::table($table)->whereNull('company_id')->count();
            if ($nullCount > 0) {
                throw new RuntimeException(
                    "Migration aborted: table '{$table}' has {$nullCount} NULL company_id row(s). "
                    .'Backfill or delete these rows before running this migration.'
                );
            }

            // SQLite (test runner) does not support MODIFY COLUMN with FK constraints — skip.
            if (DB::getDriverName() === 'sqlite') {
                continue;
            }

            DB::statement("ALTER TABLE `{$table}` MODIFY `company_id` BIGINT UNSIGNED NOT NULL");
        }

        // ── 2. Fix employee_leave_balances unique index to include company_id ──
        if (Schema::hasTable('employee_leave_balances')) {
            // Drop old partial unique index (employee_id, leave_type_id, year only)
            $this->dropIndexIfExists('employee_leave_balances', 'employee_leave_balances_unique');

            // Re-create with company_id included for proper tenant isolation
            if (DB::getDriverName() !== 'sqlite') {
                DB::statement(
                    'ALTER TABLE `employee_leave_balances` '
                    .'ADD UNIQUE INDEX `employee_leave_balances_company_unique` '
                    .'(`company_id`, `employee_id`, `leave_type_id`, `year`)'
                );
            }
        }
    }

    public function down(): void
    {
        // ── Revert NOT NULL → nullable ─────────────────────────────────────────
        foreach ($this->tenantTables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'company_id')) {
                continue;
            }
            if (DB::getDriverName() === 'sqlite') {
                continue;
            }
            DB::statement("ALTER TABLE `{$table}` MODIFY `company_id` BIGINT UNSIGNED NULL");
        }

        // ── Revert leave balance unique index ─────────────────────────────────
        if (Schema::hasTable('employee_leave_balances') && DB::getDriverName() !== 'sqlite') {
            $this->dropIndexIfExists('employee_leave_balances', 'employee_leave_balances_company_unique');

            DB::statement(
                'ALTER TABLE `employee_leave_balances` '
                .'ADD UNIQUE INDEX `employee_leave_balances_unique` '
                .'(`employee_id`, `leave_type_id`, `year`)'
            );
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $exists = DB::selectOne(
            'SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.STATISTICS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );

        if ($exists && (int) $exists->cnt > 0) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
};
