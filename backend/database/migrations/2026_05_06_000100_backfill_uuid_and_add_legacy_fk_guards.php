<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Backfill UUID mirror columns for legacy rows created before dual-write hooks.
        DB::statement("UPDATE leave_requests lr JOIN users u ON u.id = lr.user_id SET lr.user_uuid = u.uuid WHERE lr.user_id IS NOT NULL AND (lr.user_uuid IS NULL OR lr.user_uuid = '')");
        DB::statement("UPDATE leave_requests lr JOIN companies c ON c.id = lr.company_id SET lr.company_uuid = c.uuid WHERE lr.company_id IS NOT NULL AND (lr.company_uuid IS NULL OR lr.company_uuid = '')");

        DB::statement("UPDATE tickets t JOIN users u ON u.id = t.user_id SET t.user_uuid = u.uuid WHERE t.user_id IS NOT NULL AND (t.user_uuid IS NULL OR t.user_uuid = '')");
        DB::statement("UPDATE tickets t JOIN users u ON u.id = t.assignee_user_id SET t.assignee_user_uuid = u.uuid WHERE t.assignee_user_id IS NOT NULL AND (t.assignee_user_uuid IS NULL OR t.assignee_user_uuid = '')");
        DB::statement("UPDATE tickets t JOIN users u ON u.id = t.resolver_user_id SET t.resolver_user_uuid = u.uuid WHERE t.resolver_user_id IS NOT NULL AND (t.resolver_user_uuid IS NULL OR t.resolver_user_uuid = '')");
        DB::statement("UPDATE tickets t JOIN companies c ON c.id = t.company_id SET t.company_uuid = c.uuid WHERE t.company_id IS NOT NULL AND (t.company_uuid IS NULL OR t.company_uuid = '')");

        DB::statement("UPDATE hcm_payroll_lines pl JOIN users u ON u.id = pl.user_id SET pl.user_uuid = u.uuid WHERE pl.user_id IS NOT NULL AND (pl.user_uuid IS NULL OR pl.user_uuid = '')");
        DB::statement("UPDATE hcm_payroll_lines pl JOIN companies c ON c.id = pl.company_id SET pl.company_uuid = c.uuid WHERE pl.company_id IS NOT NULL AND (pl.company_uuid IS NULL OR pl.company_uuid = '')");
        DB::statement("UPDATE hcm_payroll_lines pl JOIN hcm_payroll_runs r ON r.id = pl.hcm_payroll_run_id SET pl.hcm_payroll_run_uuid = r.uuid WHERE pl.hcm_payroll_run_id IS NOT NULL AND (pl.hcm_payroll_run_uuid IS NULL OR pl.hcm_payroll_run_uuid = '')");
        DB::statement("UPDATE hcm_payroll_lines pl JOIN hcm_salary_components sc ON sc.id = pl.hcm_salary_component_id SET pl.hcm_salary_component_uuid = sc.uuid WHERE pl.hcm_salary_component_id IS NOT NULL AND (pl.hcm_salary_component_uuid IS NULL OR pl.hcm_salary_component_uuid = '')");

        DB::statement("UPDATE attendance_records SET status = 'leave' WHERE status = 'on_leave'");
    }

    public function down(): void
    {
        // No-op: this migration is backfill-only in UUID-first schema.
    }
};
