<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $idRelations = [
            ['asset_assignments', 'company_id', 'companies', 'id', 'null'],
            ['asset_assignments', 'asset_id', 'assets', 'id', 'null'],
            ['asset_assignments', 'employee_id', 'users', 'id', 'null'],
            ['asset_attachments', 'company_id', 'companies', 'id', 'null'],
            ['asset_attachments', 'asset_id', 'assets', 'id', 'null'],
            ['asset_categories', 'company_id', 'companies', 'id', 'null'],
            ['asset_logs', 'company_id', 'companies', 'id', 'null'],
            ['asset_logs', 'asset_id', 'assets', 'id', 'null'],
            ['assets', 'company_id', 'companies', 'id', 'null'],
            ['assets', 'asset_category_id', 'asset_categories', 'id', 'null'],
            ['attendance_records', 'company_id', 'companies', 'id', 'null'],
            ['departments', 'company_id', 'companies', 'id', 'null'],
            ['designations', 'company_id', 'companies', 'id', 'null'],
            ['domains', 'company_id', 'companies', 'id', 'null'],
            ['employee_leave_balances', 'company_id', 'companies', 'id', 'null'],
            ['employee_profiles', 'company_id', 'companies', 'id', 'null'],
            ['export_reconciliation_evidences', 'company_id', 'companies', 'id', 'null'],
            ['export_reconciliation_evidences', 'exported_by_user_id', 'users', 'id', 'null'],
            ['hcm_employee_payroll_item_assignments', 'company_id', 'companies', 'id', 'null'],
            ['hcm_manual_activities', 'company_id', 'companies', 'id', 'cascade'],
            ['hcm_manual_activities', 'created_by_user_id', 'users', 'id', 'null'],
            ['hcm_manual_activities', 'updated_by_user_id', 'users', 'id', 'null'],
            ['hcm_overtime_types', 'company_id', 'companies', 'id', 'null'],
            ['hcm_payroll_items', 'company_id', 'companies', 'id', 'null'],
            ['hcm_payroll_lines', 'company_id', 'companies', 'id', 'null'],
            ['hcm_payroll_periods', 'company_id', 'companies', 'id', 'null'],
            ['hcm_payroll_runs', 'company_id', 'companies', 'id', 'null'],
            ['hcm_salary_components', 'company_id', 'companies', 'id', 'null'],
            ['hcm_schedule_timings', 'company_id', 'companies', 'id', 'null'],
            ['hcm_shifts', 'company_id', 'companies', 'id', 'null'],
            ['hcm_thr_batches', 'company_id', 'companies', 'id', 'null'],
            ['hcm_thr_yearly_settings', 'company_id', 'companies', 'id', 'null'],
            ['holiday_calendars', 'company_id', 'companies', 'id', 'null'],
            ['leave_approval_workflows', 'company_id', 'companies', 'id', 'null'],
            ['leave_approvals', 'company_id', 'companies', 'id', 'null'],
            ['leave_approvals', 'leave_request_id', 'leave_requests', 'id', 'cascade'],
            ['leave_blackout_dates', 'company_id', 'companies', 'id', 'null'],
            ['leave_ledger', 'company_id', 'companies', 'id', 'null'],
            ['leave_policies', 'company_id', 'companies', 'id', 'null'],
            ['leave_policy_assignments', 'company_id', 'companies', 'id', 'null'],
            ['leave_requests', 'company_id', 'companies', 'id', 'null'],
            ['leave_types', 'company_id', 'companies', 'id', 'null'],
            ['overtime_requests', 'company_id', 'companies', 'id', 'null'],
            ['performance_reviews', 'company_id', 'companies', 'id', 'null'],
            ['report_data_blocks', 'snapshot_id', 'report_snapshots', 'id', 'cascade'],
            ['report_exports', 'snapshot_id', 'report_snapshots', 'id', 'cascade'],
            ['report_filters', 'snapshot_id', 'report_snapshots', 'id', 'cascade'],
            ['report_snapshots', 'company_id', 'companies', 'id', 'null'],
            ['teams', 'company_id', 'companies', 'id', 'null'],
            ['transactions', 'subscription_id', 'subscriptions', 'id', 'null'],
        ];

        $uuidRelations = [
            ['asset_assignments', 'company_uuid', 'companies', 'uuid', 'null'],
            ['asset_attachments', 'company_uuid', 'companies', 'uuid', 'null'],
            ['asset_categories', 'company_uuid', 'companies', 'uuid', 'null'],
            ['asset_logs', 'company_uuid', 'companies', 'uuid', 'null'],
            ['assets', 'company_uuid', 'companies', 'uuid', 'null'],
            ['domains', 'company_uuid', 'companies', 'uuid', 'null'],
            ['employee_leave_balances', 'company_uuid', 'companies', 'uuid', 'null'],
            ['employee_profiles', 'company_uuid', 'companies', 'uuid', 'null'],
            ['hcm_manual_activities', 'company_uuid', 'companies', 'uuid', 'null'],
            ['hcm_payroll_items', 'hcm_salary_component_uuid', 'hcm_salary_components', 'uuid', 'null'],
            ['hcm_payroll_lines', 'hcm_salary_component_uuid', 'hcm_salary_components', 'uuid', 'null'],
            ['hcm_schedule_timings', 'company_uuid', 'companies', 'uuid', 'null'],
            ['holiday_calendars', 'company_uuid', 'companies', 'uuid', 'null'],
            ['leave_approvals', 'company_uuid', 'companies', 'uuid', 'null'],
            ['leave_approvals', 'leave_request_uuid', 'leave_requests', 'uuid', 'cascade'],
            ['leave_ledger', 'company_uuid', 'companies', 'uuid', 'null'],
            ['leave_policies', 'company_uuid', 'companies', 'uuid', 'null'],
            ['leave_policy_assignments', 'company_uuid', 'companies', 'uuid', 'null'],
            ['leave_requests', 'company_uuid', 'companies', 'uuid', 'null'],
            ['leave_types', 'company_uuid', 'companies', 'uuid', 'null'],
            ['performance_reviews', 'company_uuid', 'companies', 'uuid', 'null'],
            ['report_snapshots', 'company_uuid', 'companies', 'uuid', 'null'],
            ['transactions', 'subscription_uuid', 'subscriptions', 'uuid', 'null'],
        ];

        foreach (array_merge($idRelations, $uuidRelations) as [$table, $column, $parentTable, $parentColumn, $onDelete]) {
            $this->nullifyOrphansWhenNullable($table, $column, $parentTable, $parentColumn);
            $this->safeForeign($table, $column, $parentTable, $parentColumn, $onDelete);
        }
    }

    public function down(): void
    {
        // Forward-only relation hardening migration.
    }

    private function safeForeign(string $table, string $column, string $parentTable, string $parentColumn, string $onDelete): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($parentTable, $parentColumn)) {
            return;
        }

        $name = $this->fkName($table, $column);

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $parentTable, $parentColumn, $name, $onDelete): void {
                $foreign = $blueprint->foreign($column, $name)->references($parentColumn)->on($parentTable);

                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } elseif ($onDelete === 'null') {
                    $foreign->nullOnDelete();
                } else {
                    $foreign->restrictOnDelete();
                }
            });
        } catch (Throwable $e) {
            $msg = strtolower($e->getMessage());
            if (
                str_contains($msg, 'duplicate')
                || str_contains($msg, 'exists')
                || str_contains($msg, 'already')
                || str_contains($msg, 'cannot add foreign key constraint')
                || str_contains($msg, 'foreign key constraint is incorrectly formed')
            ) {
                return;
            }

            throw $e;
        }
    }

    private function nullifyOrphansWhenNullable(string $table, string $column, string $parentTable, string $parentColumn): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($parentTable, $parentColumn)) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (! $this->isNullableColumn($table, $column)) {
            return;
        }

        DB::statement("UPDATE {$table} t LEFT JOIN {$parentTable} p ON t.{$column} = p.{$parentColumn} SET t.{$column} = NULL WHERE t.{$column} IS NOT NULL AND p.{$parentColumn} IS NULL");
    }

    private function isNullableColumn(string $table, string $column): bool
    {
        $row = DB::table('information_schema.COLUMNS')
            ->select('IS_NULLABLE')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->first();

        return strtoupper((string) ($row->IS_NULLABLE ?? 'NO')) === 'YES';
    }

    private function fkName(string $table, string $column): string
    {
        $base = $table.'_'.$column.'_fk';
        if (strlen($base) <= 64) {
            return $base;
        }

        return substr($table, 0, 24).'_'.substr($column, 0, 24).'_'.substr(md5($base), 0, 10);
    }
};
