<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            // ============================================================================
            // PHASE 1: ADD MISSING ASSET MANAGEMENT FK CONSTRAINTS
            // ============================================================================
            
            // Asset Categories -> Companies
            if (Schema::hasColumn('asset_categories', 'company_id') &&
                !$this->constraintExists('asset_categories', 'asset_categories_company_id_foreign')) {
                Schema::table('asset_categories', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            // Assets -> Companies & Asset Categories
            if (Schema::hasColumn('assets', 'company_id') &&
                !$this->constraintExists('assets', 'assets_company_id_foreign')) {
                Schema::table('assets', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('assets', 'asset_category_id') &&
                !$this->constraintExists('assets', 'assets_asset_category_id_foreign')) {
                Schema::table('assets', function (Blueprint $table) {
                    $table->foreign('asset_category_id')
                        ->references('id')
                        ->on('asset_categories')
                        ->restrictOnDelete();
                });
            }

            // Asset Assignments -> Companies, Assets, Employee Profiles
            if (Schema::hasColumn('asset_assignments', 'company_id') &&
                !$this->constraintExists('asset_assignments', 'asset_assignments_company_id_foreign')) {
                Schema::table('asset_assignments', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('asset_assignments', 'asset_id') &&
                !$this->constraintExists('asset_assignments', 'asset_assignments_asset_id_foreign')) {
                Schema::table('asset_assignments', function (Blueprint $table) {
                    $table->foreign('asset_id')
                        ->references('id')
                        ->on('assets')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('asset_assignments', 'employee_id') &&
                !$this->constraintExists('asset_assignments', 'asset_assignments_employee_id_foreign')) {
                Schema::table('asset_assignments', function (Blueprint $table) {
                    $table->foreign('employee_id')
                        ->references('id')
                        ->on('employee_profiles')
                        ->nullOnDelete();
                });
            }

            // Asset Logs -> Companies, Assets, Users
            if (Schema::hasColumn('asset_logs', 'company_id') &&
                !$this->constraintExists('asset_logs', 'asset_logs_company_id_foreign')) {
                Schema::table('asset_logs', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('asset_logs', 'asset_id') &&
                !$this->constraintExists('asset_logs', 'asset_logs_asset_id_foreign')) {
                Schema::table('asset_logs', function (Blueprint $table) {
                    $table->foreign('asset_id')
                        ->references('id')
                        ->on('assets')
                        ->cascadeOnDelete();
                });
            }

            // Asset Attachments -> Companies, Assets, Users
            if (Schema::hasColumn('asset_attachments', 'company_id') &&
                !$this->constraintExists('asset_attachments', 'asset_attachments_company_id_foreign')) {
                Schema::table('asset_attachments', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('asset_attachments', 'asset_id') &&
                !$this->constraintExists('asset_attachments', 'asset_attachments_asset_id_foreign')) {
                Schema::table('asset_attachments', function (Blueprint $table) {
                    $table->foreign('asset_id')
                        ->references('id')
                        ->on('assets')
                        ->cascadeOnDelete();
                });
            }

            // ============================================================================
            // PHASE 2: ADD MISSING ATTENDANCE & CORE BUSINESS FK CONSTRAINTS
            // ============================================================================
            
            if (Schema::hasColumn('attendance_records', 'company_id') &&
                !$this->constraintExists('attendance_records', 'attendance_records_company_id_foreign')) {
                Schema::table('attendance_records', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('departments', 'company_id') &&
                !$this->constraintExists('departments', 'departments_company_id_foreign')) {
                Schema::table('departments', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('designations', 'company_id') &&
                !$this->constraintExists('designations', 'designations_company_id_foreign')) {
                Schema::table('designations', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('teams', 'company_id') &&
                !$this->constraintExists('teams', 'teams_company_id_foreign')) {
                Schema::table('teams', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('employee_profiles', 'company_id') &&
                !$this->constraintExists('employee_profiles', 'employee_profiles_company_id_foreign')) {
                Schema::table('employee_profiles', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('employee_leave_balances', 'company_id') &&
                !$this->constraintExists('employee_leave_balances', 'employee_leave_balances_company_id_foreign')) {
                Schema::table('employee_leave_balances', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            // ============================================================================
            // PHASE 3: ADD MISSING HCM PAYROLL FK CONSTRAINTS
            // ============================================================================
            
            if (Schema::hasColumn('hcm_overtime_types', 'company_id') &&
                !$this->constraintExists('hcm_overtime_types', 'hcm_overtime_types_company_id_foreign')) {
                Schema::table('hcm_overtime_types', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('hcm_salary_components', 'company_id') &&
                !$this->constraintExists('hcm_salary_components', 'hcm_salary_components_company_id_foreign')) {
                Schema::table('hcm_salary_components', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('hcm_schedule_timings', 'company_id') &&
                !$this->constraintExists('hcm_schedule_timings', 'hcm_schedule_timings_company_id_foreign')) {
                Schema::table('hcm_schedule_timings', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('hcm_shifts', 'company_id') &&
                !$this->constraintExists('hcm_shifts', 'hcm_shifts_company_id_foreign')) {
                Schema::table('hcm_shifts', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('hcm_payroll_periods', 'company_id') &&
                !$this->constraintExists('hcm_payroll_periods', 'hcm_payroll_periods_company_id_foreign')) {
                Schema::table('hcm_payroll_periods', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('hcm_payroll_runs', 'company_id') &&
                !$this->constraintExists('hcm_payroll_runs', 'hcm_payroll_runs_company_id_foreign')) {
                Schema::table('hcm_payroll_runs', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('hcm_payroll_runs', 'hcm_payroll_period_id') &&
                !$this->constraintExists('hcm_payroll_runs', 'hcm_payroll_runs_hcm_payroll_period_id_foreign')) {
                Schema::table('hcm_payroll_runs', function (Blueprint $table) {
                    $table->foreign('hcm_payroll_period_id')
                        ->references('id')
                        ->on('hcm_payroll_periods')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('hcm_payroll_lines', 'company_id') &&
                !$this->constraintExists('hcm_payroll_lines', 'hcm_payroll_lines_company_id_foreign')) {
                Schema::table('hcm_payroll_lines', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('hcm_payroll_lines', 'hcm_payroll_run_id') &&
                !$this->constraintExists('hcm_payroll_lines', 'hcm_payroll_lines_hcm_payroll_run_id_foreign')) {
                Schema::table('hcm_payroll_lines', function (Blueprint $table) {
                    $table->foreign('hcm_payroll_run_id')
                        ->references('id')
                        ->on('hcm_payroll_runs')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('hcm_payroll_lines', 'hcm_salary_component_id') &&
                !$this->constraintExists('hcm_payroll_lines', 'hcm_payroll_lines_hcm_salary_component_id_foreign')) {
                Schema::table('hcm_payroll_lines', function (Blueprint $table) {
                    $table->foreign('hcm_salary_component_id')
                        ->references('id')
                        ->on('hcm_salary_components')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('hcm_payroll_items', 'company_id') &&
                !$this->constraintExists('hcm_payroll_items', 'hcm_payroll_items_company_id_foreign')) {
                Schema::table('hcm_payroll_items', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('hcm_payroll_items', 'hcm_salary_component_id') &&
                !$this->constraintExists('hcm_payroll_items', 'hcm_payroll_items_hcm_salary_component_id_foreign')) {
                Schema::table('hcm_payroll_items', function (Blueprint $table) {
                    $table->foreign('hcm_salary_component_id')
                        ->references('id')
                        ->on('hcm_salary_components')
                        ->cascadeOnDelete();
                });
            }

            // ============================================================================
            // PHASE 4: ADD MISSING THR FK CONSTRAINTS
            // ============================================================================
            
            if (Schema::hasColumn('hcm_thr_yearly_settings', 'company_id') &&
                !$this->constraintExists('hcm_thr_yearly_settings', 'hcm_thr_yearly_settings_company_id_foreign')) {
                Schema::table('hcm_thr_yearly_settings', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('hcm_thr_batches', 'company_id') &&
                !$this->constraintExists('hcm_thr_batches', 'hcm_thr_batches_company_id_foreign')) {
                Schema::table('hcm_thr_batches', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('hcm_thr_batches', 'hcm_payroll_period_id') &&
                !$this->constraintExists('hcm_thr_batches', 'hcm_thr_batches_hcm_payroll_period_id_foreign')) {
                Schema::table('hcm_thr_batches', function (Blueprint $table) {
                    $table->foreign('hcm_payroll_period_id')
                        ->references('id')
                        ->on('hcm_payroll_periods')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('hcm_thr_batches', 'hcm_payroll_run_id') &&
                !$this->constraintExists('hcm_thr_batches', 'hcm_thr_batches_hcm_payroll_run_id_foreign')) {
                Schema::table('hcm_thr_batches', function (Blueprint $table) {
                    $table->foreign('hcm_payroll_run_id')
                        ->references('id')
                        ->on('hcm_payroll_runs')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('hcm_thr_batches', 'hcm_thr_yearly_setting_id') &&
                !$this->constraintExists('hcm_thr_batches', 'hcm_thr_batches_hcm_thr_yearly_setting_id_foreign')) {
                Schema::table('hcm_thr_batches', function (Blueprint $table) {
                    $table->foreign('hcm_thr_yearly_setting_id')
                        ->references('id')
                        ->on('hcm_thr_yearly_settings')
                        ->cascadeOnDelete();
                });
            }

            // ============================================================================
            // PHASE 5: ADD MISSING LEAVE MANAGEMENT FK CONSTRAINTS
            // ============================================================================
            
            if (Schema::hasColumn('leave_types', 'company_id') &&
                !$this->constraintExists('leave_types', 'leave_types_company_id_foreign')) {
                Schema::table('leave_types', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('leave_policies', 'company_id') &&
                !$this->constraintExists('leave_policies', 'leave_policies_company_id_foreign')) {
                Schema::table('leave_policies', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('leave_policy_assignments', 'company_id') &&
                !$this->constraintExists('leave_policy_assignments', 'leave_policy_assignments_company_id_foreign')) {
                Schema::table('leave_policy_assignments', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('leave_requests', 'company_id') &&
                !$this->constraintExists('leave_requests', 'leave_requests_company_id_foreign')) {
                Schema::table('leave_requests', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('leave_approval_workflows', 'company_id') &&
                !$this->constraintExists('leave_approval_workflows', 'leave_approval_workflows_company_id_foreign')) {
                Schema::table('leave_approval_workflows', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('leave_approvals', 'company_id') &&
                !$this->constraintExists('leave_approvals', 'leave_approvals_company_id_foreign')) {
                Schema::table('leave_approvals', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('leave_ledger', 'company_id') &&
                !$this->constraintExists('leave_ledger', 'leave_ledger_company_id_foreign')) {
                Schema::table('leave_ledger', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('leave_blackout_dates', 'company_id') &&
                !$this->constraintExists('leave_blackout_dates', 'leave_blackout_dates_company_id_foreign')) {
                Schema::table('leave_blackout_dates', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('holiday_calendars', 'company_id') &&
                !$this->constraintExists('holiday_calendars', 'holiday_calendars_company_id_foreign')) {
                Schema::table('holiday_calendars', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            // ============================================================================
            // PHASE 6: ADD MISSING REPORTING FK CONSTRAINTS
            // ============================================================================
            
            if (Schema::hasColumn('report_snapshots', 'company_id') &&
                !$this->constraintExists('report_snapshots', 'report_snapshots_company_id_foreign')) {
                Schema::table('report_snapshots', function (Blueprint $table) {
                    $table->foreign('company_id')
                        ->references('id')
                        ->on('companies')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('report_data_blocks', 'snapshot_id') &&
                !$this->constraintExists('report_data_blocks', 'report_data_blocks_snapshot_id_foreign')) {
                Schema::table('report_data_blocks', function (Blueprint $table) {
                    $table->foreign('snapshot_id')
                        ->references('id')
                        ->on('report_snapshots')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('report_filters', 'snapshot_id') &&
                !$this->constraintExists('report_filters', 'report_filters_snapshot_id_foreign')) {
                Schema::table('report_filters', function (Blueprint $table) {
                    $table->foreign('snapshot_id')
                        ->references('id')
                        ->on('report_snapshots')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('report_exports', 'snapshot_id') &&
                !$this->constraintExists('report_exports', 'report_exports_snapshot_id_foreign')) {
                Schema::table('report_exports', function (Blueprint $table) {
                    $table->foreign('snapshot_id')
                        ->references('id')
                        ->on('report_snapshots')
                        ->cascadeOnDelete();
                });
            }

            // ============================================================================
            // PHASE 7: ADD MISSING TRANSACTION & SESSION FK CONSTRAINTS
            // ============================================================================
            
            if (Schema::hasColumn('transactions', 'subscription_id') &&
                Schema::hasTable('subscriptions') &&
                !$this->constraintExists('transactions', 'transactions_subscription_id_foreign')) {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->foreign('subscription_id')
                        ->references('id')
                        ->on('subscriptions')
                        ->cascadeOnDelete();
                });
            }

            if (Schema::hasColumn('sessions', 'user_id') &&
                !$this->constraintExists('sessions', 'sessions_user_id_foreign')) {
                Schema::table('sessions', function (Blueprint $table) {
                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                });
            }

        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::disableForeignKeyConstraints();

        $constraints = [
            // Asset management
            ['asset_categories', 'asset_categories_company_id_foreign'],
            ['assets', 'assets_company_id_foreign'],
            ['assets', 'assets_asset_category_id_foreign'],
            ['asset_assignments', 'asset_assignments_company_id_foreign'],
            ['asset_assignments', 'asset_assignments_asset_id_foreign'],
            ['asset_assignments', 'asset_assignments_employee_id_foreign'],
            ['asset_logs', 'asset_logs_company_id_foreign'],
            ['asset_logs', 'asset_logs_asset_id_foreign'],
            ['asset_attachments', 'asset_attachments_company_id_foreign'],
            ['asset_attachments', 'asset_attachments_asset_id_foreign'],
            // Core business
            ['attendance_records', 'attendance_records_company_id_foreign'],
            ['departments', 'departments_company_id_foreign'],
            ['designations', 'designations_company_id_foreign'],
            ['teams', 'teams_company_id_foreign'],
            ['employee_profiles', 'employee_profiles_company_id_foreign'],
            ['employee_leave_balances', 'employee_leave_balances_company_id_foreign'],
            // HCM
            ['hcm_overtime_types', 'hcm_overtime_types_company_id_foreign'],
            ['hcm_salary_components', 'hcm_salary_components_company_id_foreign'],
            ['hcm_schedule_timings', 'hcm_schedule_timings_company_id_foreign'],
            ['hcm_shifts', 'hcm_shifts_company_id_foreign'],
            ['hcm_payroll_periods', 'hcm_payroll_periods_company_id_foreign'],
            ['hcm_payroll_runs', 'hcm_payroll_runs_company_id_foreign'],
            ['hcm_payroll_runs', 'hcm_payroll_runs_hcm_payroll_period_id_foreign'],
            ['hcm_payroll_lines', 'hcm_payroll_lines_company_id_foreign'],
            ['hcm_payroll_lines', 'hcm_payroll_lines_hcm_payroll_run_id_foreign'],
            ['hcm_payroll_lines', 'hcm_payroll_lines_hcm_salary_component_id_foreign'],
            ['hcm_payroll_items', 'hcm_payroll_items_company_id_foreign'],
            ['hcm_payroll_items', 'hcm_payroll_items_hcm_salary_component_id_foreign'],
            ['hcm_thr_yearly_settings', 'hcm_thr_yearly_settings_company_id_foreign'],
            ['hcm_thr_batches', 'hcm_thr_batches_company_id_foreign'],
            ['hcm_thr_batches', 'hcm_thr_batches_hcm_payroll_period_id_foreign'],
            ['hcm_thr_batches', 'hcm_thr_batches_hcm_payroll_run_id_foreign'],
            ['hcm_thr_batches', 'hcm_thr_batches_hcm_thr_yearly_setting_id_foreign'],
            // Leave management
            ['leave_types', 'leave_types_company_id_foreign'],
            ['leave_policies', 'leave_policies_company_id_foreign'],
            ['leave_policy_assignments', 'leave_policy_assignments_company_id_foreign'],
            ['leave_requests', 'leave_requests_company_id_foreign'],
            ['leave_approval_workflows', 'leave_approval_workflows_company_id_foreign'],
            ['leave_approvals', 'leave_approvals_company_id_foreign'],
            ['leave_ledger', 'leave_ledger_company_id_foreign'],
            ['leave_blackout_dates', 'leave_blackout_dates_company_id_foreign'],
            ['holiday_calendars', 'holiday_calendars_company_id_foreign'],
            // Reporting
            ['report_snapshots', 'report_snapshots_company_id_foreign'],
            ['report_data_blocks', 'report_data_blocks_snapshot_id_foreign'],
            ['report_filters', 'report_filters_snapshot_id_foreign'],
            ['report_exports', 'report_exports_snapshot_id_foreign'],
            // Transactions & Sessions
            ['transactions', 'transactions_subscription_id_foreign'],
            ['sessions', 'sessions_user_id_foreign'],
        ];

        foreach ($constraints as [$table, $constraint]) {
            if (Schema::hasTable($table) && $this->constraintExists($table, $constraint)) {
                Schema::table($table, function (Blueprint $table) use ($constraint) {
                    $table->dropForeign([$constraint]);
                });
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Helper function to check if a constraint exists
     */
    private function constraintExists(string $tableName, string $constraintName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        $constraints = \DB::select("
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
        ", [env('DB_DATABASE'), $tableName, $constraintName]);

        return count($constraints) > 0;
    }
};
