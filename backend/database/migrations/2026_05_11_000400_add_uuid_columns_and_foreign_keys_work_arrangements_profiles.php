<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Stage-4 UUID completion:
 *
 * hcm_payroll_work_profiles:
 *   - tambah kolom uuid (UNIQUE), company_uuid
 *   - backfill uuid dari Str::uuid(), company_uuid dari companies.uuid via company_id
 *   - FK: company_uuid → companies.uuid (nullOnDelete)
 *
 * hcm_employee_work_arrangements:
 *   - tambah kolom company_uuid, user_uuid
 *   - backfill dari companies.uuid / users.uuid via company_id / user_id
 *   - backfill hcm_payroll_work_profile_uuid dari hcm_payroll_work_profiles.uuid via hcm_payroll_work_profile_id
 *   - FK: company_uuid → companies.uuid, user_uuid → users.uuid,
 *         hcm_payroll_work_profile_uuid → hcm_payroll_work_profiles.uuid
 *
 * Forward-only migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumns();
        $this->backfill();
        $this->addForeignKeys();
    }

    public function down(): void
    {
        // Forward-only.
    }

    // ─── Column additions ─────────────────────────────────────────────────────

    private function addColumns(): void
    {
        Schema::table('hcm_payroll_work_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('hcm_payroll_work_profiles', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('hcm_payroll_work_profiles', 'company_uuid')) {
                $table->uuid('company_uuid')->nullable()->after('company_id');
            }
        });

        Schema::table('hcm_employee_work_arrangements', function (Blueprint $table) {
            if (! Schema::hasColumn('hcm_employee_work_arrangements', 'company_uuid')) {
                $table->uuid('company_uuid')->nullable()->after('company_id');
            }
            if (! Schema::hasColumn('hcm_employee_work_arrangements', 'user_uuid')) {
                $table->uuid('user_uuid')->nullable()->after('user_id');
            }
        });
    }

    // ─── Backfill ─────────────────────────────────────────────────────────────

    private function backfill(): void
    {
        // 1. hcm_payroll_work_profiles: row uuid
        DB::table('hcm_payroll_work_profiles')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('hcm_payroll_work_profiles')
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        // 2. hcm_payroll_work_profiles: company_uuid
        $this->syncUuidByJoin('hcm_payroll_work_profiles', 'company_id', 'company_uuid', 'companies');

        // 3. hcm_employee_work_arrangements: company_uuid + user_uuid
        $this->syncUuidByJoin('hcm_employee_work_arrangements', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('hcm_employee_work_arrangements', 'user_id', 'user_uuid', 'users');

        // 4. hcm_employee_work_arrangements: hcm_payroll_work_profile_uuid via hcm_payroll_work_profile_id
        $this->syncUuidByJoin(
            'hcm_employee_work_arrangements',
            'hcm_payroll_work_profile_id',
            'hcm_payroll_work_profile_uuid',
            'hcm_payroll_work_profiles'
        );
    }

    private function syncUuidByJoin(string $table, string $legacyColumn, string $uuidColumn, string $parentTable): void
    {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $legacyColumn)
            || ! Schema::hasColumn($table, $uuidColumn)
            || ! Schema::hasTable($parentTable)
            || ! Schema::hasColumn($parentTable, 'uuid')
        ) {
            return;
        }

        DB::statement("
            UPDATE `{$table}` t
            JOIN `{$parentTable}` p ON p.id = t.`{$legacyColumn}`
            SET t.`{$uuidColumn}` = p.uuid
            WHERE t.`{$uuidColumn}` IS NULL
              AND t.`{$legacyColumn}` IS NOT NULL
        ");
    }

    // ─── Foreign keys ─────────────────────────────────────────────────────────

    private function addForeignKeys(): void
    {
        $this->safeForeign(
            'hcm_payroll_work_profiles', 'company_uuid',
            'companies', 'uuid',
            'hcm_payroll_work_profiles_company_uuid_fk', 'null'
        );

        $this->safeForeign(
            'hcm_employee_work_arrangements', 'company_uuid',
            'companies', 'uuid',
            'hcm_emp_work_arr_company_uuid_fk', 'null'
        );

        $this->safeForeign(
            'hcm_employee_work_arrangements', 'user_uuid',
            'users', 'uuid',
            'hcm_emp_work_arr_user_uuid_fk', 'null'
        );

        $this->safeForeign(
            'hcm_employee_work_arrangements', 'hcm_payroll_work_profile_uuid',
            'hcm_payroll_work_profiles', 'uuid',
            'hcm_emp_work_arr_profile_uuid_fk', 'null'
        );
    }

    private function safeForeign(
        string $table,
        string $column,
        string $parentTable,
        string $parentColumn,
        string $name,
        string $onDelete
    ): void {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $column)
            || ! Schema::hasTable($parentTable)
            || ! Schema::hasColumn($parentTable, $parentColumn)
        ) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($column, $parentTable, $parentColumn, $name, $onDelete): void {
                $foreign = $tableBlueprint->foreign($column, $name)->references($parentColumn)->on($parentTable);

                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } elseif ($onDelete === 'null') {
                    $foreign->nullOnDelete();
                } elseif ($onDelete === 'restrict') {
                    $foreign->restrictOnDelete();
                }
            });
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }
};
