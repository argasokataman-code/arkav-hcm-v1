<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'teams',
            'employee_assignments',
            'hcm_salary_components',
            'hcm_resignations',
            'hcm_terminations',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if ($tableName === 'teams' && ! Schema::hasColumn($tableName, 'department_uuid')) {
                    $table->uuid('department_uuid')->nullable()->after('department_id');
                }

                if ($tableName === 'employee_assignments') {
                    if (! Schema::hasColumn($tableName, 'employee_uuid')) {
                        $table->uuid('employee_uuid')->nullable()->after('employee_id');
                    }
                    if (! Schema::hasColumn($tableName, 'department_uuid')) {
                        $table->uuid('department_uuid')->nullable()->after('department_id');
                    }
                    if (! Schema::hasColumn($tableName, 'designation_uuid')) {
                        $table->uuid('designation_uuid')->nullable()->after('designation_id');
                    }
                    if (! Schema::hasColumn($tableName, 'team_uuid')) {
                        $table->uuid('team_uuid')->nullable()->after('team_id');
                    }
                    if (! Schema::hasColumn($tableName, 'manager_user_uuid')) {
                        $table->uuid('manager_user_uuid')->nullable()->after('manager_user_id');
                    }
                }

                if (Schema::hasColumn($tableName, 'company_id') && ! Schema::hasColumn($tableName, 'company_uuid')) {
                    $table->uuid('company_uuid')->nullable()->after('company_id');
                }

                if (in_array($tableName, ['hcm_resignations', 'hcm_terminations'], true) && ! Schema::hasColumn($tableName, 'user_uuid')) {
                    $table->uuid('user_uuid')->nullable()->after('user_id');
                }
            });
        }

        $this->backfillRowUuids($tables);
        $this->backfillForeignUuids();
        $this->addIndexes();
        $this->addForeignKeys();
    }

    public function down(): void
    {
        // Forward-only batch.
    }

    private function backfillRowUuids(array $tables): void
    {
        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'uuid')) {
                continue;
            }

            DB::table($tableName)
                ->whereNull('uuid')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($tableName): void {
                    foreach ($rows as $row) {
                        DB::table($tableName)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
                    }
                }, 'id');
        }
    }

    private function backfillForeignUuids(): void
    {
        $this->syncUuidByJoin('teams', 'department_id', 'department_uuid', 'departments');

        $this->syncUuidByJoin('employee_assignments', 'employee_id', 'employee_uuid', 'employee_profiles');
        $this->syncUuidByJoin('employee_assignments', 'department_id', 'department_uuid', 'departments');
        $this->syncUuidByJoin('employee_assignments', 'designation_id', 'designation_uuid', 'designations');
        $this->syncUuidByJoin('employee_assignments', 'team_id', 'team_uuid', 'teams');
        $this->syncUuidByJoin('employee_assignments', 'manager_user_id', 'manager_user_uuid', 'users');

        $this->syncUuidByJoin('hcm_salary_components', 'company_id', 'company_uuid', 'companies');

        $this->syncUuidByJoin('hcm_resignations', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('hcm_resignations', 'user_id', 'user_uuid', 'users');

        $this->syncUuidByJoin('hcm_terminations', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('hcm_terminations', 'user_id', 'user_uuid', 'users');
    }

    private function syncUuidByJoin(string $table, string $legacyColumn, string $uuidColumn, string $parentTable): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $legacyColumn) || ! Schema::hasColumn($table, $uuidColumn)) {
            return;
        }

        if (! Schema::hasTable($parentTable) || ! Schema::hasColumn($parentTable, 'uuid')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE {$table} t JOIN {$parentTable} p ON t.{$legacyColumn} = p.id SET t.{$uuidColumn} = p.uuid WHERE t.{$legacyColumn} IS NOT NULL AND t.{$uuidColumn} IS NULL");
            return;
        }

        $rows = DB::table($table)->whereNotNull($legacyColumn)->whereNull($uuidColumn)->select('id', $legacyColumn)->get();
        foreach ($rows as $row) {
            $uuid = DB::table($parentTable)->where('id', $row->{$legacyColumn})->value('uuid');
            DB::table($table)->where('id', $row->id)->update([$uuidColumn => $uuid]);
        }
    }

    private function addIndexes(): void
    {
        $indexes = [
            ['teams', 'department_uuid', 'teams_department_uuid_idx'],
            ['employee_assignments', 'employee_uuid', 'employee_assignments_employee_uuid_idx'],
            ['employee_assignments', 'department_uuid', 'employee_assignments_department_uuid_idx'],
            ['employee_assignments', 'designation_uuid', 'employee_assignments_designation_uuid_idx'],
            ['employee_assignments', 'team_uuid', 'employee_assignments_team_uuid_idx'],
            ['employee_assignments', 'manager_user_uuid', 'employee_assignments_manager_user_uuid_idx'],
            ['hcm_salary_components', 'company_uuid', 'hcm_salary_components_company_uuid_idx'],
            ['hcm_resignations', 'company_uuid', 'hcm_resignations_company_uuid_idx'],
            ['hcm_resignations', 'user_uuid', 'hcm_resignations_user_uuid_idx'],
            ['hcm_terminations', 'company_uuid', 'hcm_terminations_company_uuid_idx'],
            ['hcm_terminations', 'user_uuid', 'hcm_terminations_user_uuid_idx'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            $this->safeIndex($table, $column, $name);
        }
    }

    private function addForeignKeys(): void
    {
        $this->safeForeign('teams', 'department_uuid', 'departments', 'uuid', 'teams_department_uuid_fk', 'null');

        $this->safeForeign('employee_assignments', 'employee_uuid', 'employee_profiles', 'uuid', 'employee_assignments_employee_uuid_fk', 'cascade');
        $this->safeForeign('employee_assignments', 'department_uuid', 'departments', 'uuid', 'employee_assignments_department_uuid_fk', 'null');
        $this->safeForeign('employee_assignments', 'designation_uuid', 'designations', 'uuid', 'employee_assignments_designation_uuid_fk', 'null');
        $this->safeForeign('employee_assignments', 'team_uuid', 'teams', 'uuid', 'employee_assignments_team_uuid_fk', 'null');
        $this->safeForeign('employee_assignments', 'manager_user_uuid', 'users', 'uuid', 'employee_assignments_manager_user_uuid_fk', 'null');

        $this->safeForeign('hcm_salary_components', 'company_uuid', 'companies', 'uuid', 'hcm_salary_components_company_uuid_fk', 'null');

        $this->safeForeign('hcm_resignations', 'company_uuid', 'companies', 'uuid', 'hcm_resignations_company_uuid_fk', 'null');
        $this->safeForeign('hcm_resignations', 'user_uuid', 'users', 'uuid', 'hcm_resignations_user_uuid_fk', 'cascade');

        $this->safeForeign('hcm_terminations', 'company_uuid', 'companies', 'uuid', 'hcm_terminations_company_uuid_fk', 'null');
        $this->safeForeign('hcm_terminations', 'user_uuid', 'users', 'uuid', 'hcm_terminations_user_uuid_fk', 'cascade');
    }

    private function safeIndex(string $table, string $column, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($column, $name): void {
                $tableBlueprint->index($column, $name);
            });
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }

    private function safeForeign(string $table, string $column, string $parentTable, string $parentColumn, string $name, string $onDelete): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($parentTable, $parentColumn)) {
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
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }
};
