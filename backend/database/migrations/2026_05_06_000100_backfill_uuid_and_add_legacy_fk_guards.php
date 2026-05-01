<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        // Ensure id columns can be referenced safely by FK constraints.
        if (! $this->isColumnUnique('users', 'id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique('id', 'users_id_unique_guard');
            });
        }

        if (! $this->isColumnUnique('companies', 'id')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->unique('id', 'companies_id_unique_guard');
            });
        }

        // Add missing legacy-id FK guards to prevent future orphan rows.
        $this->addForeignIfMissing('hcm_user_roles', 'fk_hcm_user_roles_user_id_guard', function (Blueprint $table): void {
            $table->foreign('user_id', 'fk_hcm_user_roles_user_id_guard')->references('id')->on('users')->cascadeOnDelete();
        });

        $this->addForeignIfMissing('hcm_user_roles', 'fk_hcm_user_roles_company_id_guard', function (Blueprint $table): void {
            $table->foreign('company_id', 'fk_hcm_user_roles_company_id_guard')->references('id')->on('companies')->cascadeOnDelete();
        });

        $this->addForeignIfMissing('hcm_user_roles', 'fk_hcm_user_roles_assigned_by_user_id_guard', function (Blueprint $table): void {
            $table->foreign('assigned_by_user_id', 'fk_hcm_user_roles_assigned_by_user_id_guard')->references('id')->on('users')->nullOnDelete();
        });

        $this->addForeignIfMissing('hcm_role_permissions', 'fk_hcm_role_permissions_company_id_guard', function (Blueprint $table): void {
            $table->foreign('company_id', 'fk_hcm_role_permissions_company_id_guard')->references('id')->on('companies')->cascadeOnDelete();
        });

        $this->addForeignIfMissing('hcm_tax_governance_policies', 'fk_hcm_tax_policies_company_id_guard', function (Blueprint $table): void {
            $table->foreign('company_id', 'fk_hcm_tax_policies_company_id_guard')->references('id')->on('companies')->cascadeOnDelete();
        });

        $this->addForeignIfMissing('hcm_tax_governance_policy_events', 'fk_hcm_tax_policy_events_company_id_guard', function (Blueprint $table): void {
            $table->foreign('company_id', 'fk_hcm_tax_policy_events_company_id_guard')->references('id')->on('companies')->cascadeOnDelete();
        });

        $this->addForeignIfMissing('hcm_billing_tax_policies', 'fk_hcm_billing_tax_policies_company_id_guard', function (Blueprint $table): void {
            $table->foreign('company_id', 'fk_hcm_billing_tax_policies_company_id_guard')->references('id')->on('companies')->cascadeOnDelete();
        });

        $this->addForeignIfMissing('hcm_billing_tax_policies', 'fk_hcm_billing_tax_policies_created_by_user_id_guard', function (Blueprint $table): void {
            $table->foreign('created_by_user_id', 'fk_hcm_billing_tax_policies_created_by_user_id_guard')->references('id')->on('users')->nullOnDelete();
        });

        $this->addForeignIfMissing('hcm_billing_tax_policies', 'fk_hcm_billing_tax_policies_updated_by_user_id_guard', function (Blueprint $table): void {
            $table->foreign('updated_by_user_id', 'fk_hcm_billing_tax_policies_updated_by_user_id_guard')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $this->dropForeignIfExists('hcm_billing_tax_policies', 'fk_hcm_billing_tax_policies_updated_by_user_id_guard');
        $this->dropForeignIfExists('hcm_billing_tax_policies', 'fk_hcm_billing_tax_policies_created_by_user_id_guard');
        $this->dropForeignIfExists('hcm_billing_tax_policies', 'fk_hcm_billing_tax_policies_company_id_guard');
        $this->dropForeignIfExists('hcm_tax_governance_policy_events', 'fk_hcm_tax_policy_events_company_id_guard');
        $this->dropForeignIfExists('hcm_tax_governance_policies', 'fk_hcm_tax_policies_company_id_guard');
        $this->dropForeignIfExists('hcm_role_permissions', 'fk_hcm_role_permissions_company_id_guard');
        $this->dropForeignIfExists('hcm_user_roles', 'fk_hcm_user_roles_assigned_by_user_id_guard');
        $this->dropForeignIfExists('hcm_user_roles', 'fk_hcm_user_roles_company_id_guard');
        $this->dropForeignIfExists('hcm_user_roles', 'fk_hcm_user_roles_user_id_guard');

        if ($this->hasIndex('companies', 'companies_id_unique_guard')) {
            Schema::table('companies', function (Blueprint $table): void {
                $table->dropUnique('companies_id_unique_guard');
            });
        }

        if ($this->hasIndex('users', 'users_id_unique_guard')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_id_unique_guard');
            });
        }
    }

    private function addForeignIfMissing(string $table, string $constraintName, \Closure $callback): void
    {
        if (! Schema::hasTable($table) || $this->hasForeign($table, $constraintName)) {
            return;
        }

        Schema::table($table, $callback);
    }

    private function dropForeignIfExists(string $table, string $constraintName): void
    {
        if (! Schema::hasTable($table) || ! $this->hasForeign($table, $constraintName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($constraintName): void {
            $blueprint->dropForeign($constraintName);
        });
    }

    private function hasForeign(string $table, string $constraintName): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_TYPE = "FOREIGN KEY" AND CONSTRAINT_NAME = ?',
            [$table, $constraintName]
        );

        return (int) ($result->total ?? 0) > 0;
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $indexName]
        );

        return (int) ($result->total ?? 0) > 0;
    }

    private function isColumnUnique(string $table, string $column): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND NON_UNIQUE = 0',
            [$table, $column]
        );

        return (int) ($result->total ?? 0) > 0;
    }
};
