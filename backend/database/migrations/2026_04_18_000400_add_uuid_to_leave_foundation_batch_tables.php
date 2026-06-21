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
            'leave_types',
            'leave_policies',
            'leave_policy_assignments',
            'employee_leave_balances',
            'leave_ledger',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if (! Schema::hasColumn($tableName, 'company_uuid')) {
                    $table->uuid('company_uuid')->nullable()->after('company_id');
                }

                if (in_array($tableName, ['leave_policies', 'employee_leave_balances', 'leave_ledger'], true) && ! Schema::hasColumn($tableName, 'leave_type_uuid')) {
                    $table->uuid('leave_type_uuid')->nullable()->after('leave_type_id');
                }

                if ($tableName === 'leave_policy_assignments' && ! Schema::hasColumn($tableName, 'policy_uuid')) {
                    $table->uuid('policy_uuid')->nullable()->after('policy_id');
                }

                if (in_array($tableName, ['leave_policy_assignments', 'employee_leave_balances', 'leave_ledger'], true) && ! Schema::hasColumn($tableName, 'employee_uuid')) {
                    $table->uuid('employee_uuid')->nullable()->after('employee_id');
                }

                if ($tableName === 'leave_ledger') {
                    if (! Schema::hasColumn($tableName, 'policy_uuid')) {
                        $table->uuid('policy_uuid')->nullable()->after('policy_id');
                    }
                    if (! Schema::hasColumn($tableName, 'created_by_uuid')) {
                        $table->uuid('created_by_uuid')->nullable()->after('created_by');
                    }
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
        $this->syncUuidByJoin('leave_types', 'company_id', 'company_uuid', 'companies');

        $this->syncUuidByJoin('leave_policies', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('leave_policies', 'leave_type_id', 'leave_type_uuid', 'leave_types');

        $this->syncUuidByJoin('leave_policy_assignments', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('leave_policy_assignments', 'policy_id', 'policy_uuid', 'leave_policies');
        $this->syncUuidByJoin('leave_policy_assignments', 'employee_id', 'employee_uuid', 'users');

        $this->syncUuidByJoin('employee_leave_balances', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('employee_leave_balances', 'employee_id', 'employee_uuid', 'users');
        $this->syncUuidByJoin('employee_leave_balances', 'leave_type_id', 'leave_type_uuid', 'leave_types');

        $this->syncUuidByJoin('leave_ledger', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('leave_ledger', 'employee_id', 'employee_uuid', 'users');
        $this->syncUuidByJoin('leave_ledger', 'leave_type_id', 'leave_type_uuid', 'leave_types');
        $this->syncUuidByJoin('leave_ledger', 'policy_id', 'policy_uuid', 'leave_policies');
        $this->syncUuidByJoin('leave_ledger', 'created_by', 'created_by_uuid', 'users');
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
            ['leave_types', 'company_uuid', 'leave_types_company_uuid_idx'],
            ['leave_policies', 'company_uuid', 'leave_policies_company_uuid_idx'],
            ['leave_policies', 'leave_type_uuid', 'leave_policies_leave_type_uuid_idx'],
            ['leave_policy_assignments', 'company_uuid', 'leave_policy_assignments_company_uuid_idx'],
            ['leave_policy_assignments', 'policy_uuid', 'leave_policy_assignments_policy_uuid_idx'],
            ['leave_policy_assignments', 'employee_uuid', 'leave_policy_assignments_employee_uuid_idx'],
            ['employee_leave_balances', 'company_uuid', 'employee_leave_balances_company_uuid_idx'],
            ['employee_leave_balances', 'employee_uuid', 'employee_leave_balances_employee_uuid_idx'],
            ['employee_leave_balances', 'leave_type_uuid', 'employee_leave_balances_leave_type_uuid_idx'],
            ['leave_ledger', 'company_uuid', 'leave_ledger_company_uuid_idx'],
            ['leave_ledger', 'employee_uuid', 'leave_ledger_employee_uuid_idx'],
            ['leave_ledger', 'leave_type_uuid', 'leave_ledger_leave_type_uuid_idx'],
            ['leave_ledger', 'policy_uuid', 'leave_ledger_policy_uuid_idx'],
            ['leave_ledger', 'created_by_uuid', 'leave_ledger_created_by_uuid_idx'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            $this->safeIndex($table, $column, $name);
        }
    }

    private function addForeignKeys(): void
    {
        $this->safeForeign('leave_types', 'company_uuid', 'companies', 'uuid', 'leave_types_company_uuid_fk', 'null');

        $this->safeForeign('leave_policies', 'company_uuid', 'companies', 'uuid', 'leave_policies_company_uuid_fk', 'null');
        $this->safeForeign('leave_policies', 'leave_type_uuid', 'leave_types', 'uuid', 'leave_policies_leave_type_uuid_fk', 'cascade');

        $this->safeForeign('leave_policy_assignments', 'company_uuid', 'companies', 'uuid', 'leave_policy_assignments_company_uuid_fk', 'null');
        $this->safeForeign('leave_policy_assignments', 'policy_uuid', 'leave_policies', 'uuid', 'leave_policy_assignments_policy_uuid_fk', 'cascade');
        $this->safeForeign('leave_policy_assignments', 'employee_uuid', 'users', 'uuid', 'leave_policy_assignments_employee_uuid_fk', 'cascade');

        $this->safeForeign('employee_leave_balances', 'company_uuid', 'companies', 'uuid', 'employee_leave_balances_company_uuid_fk', 'null');
        $this->safeForeign('employee_leave_balances', 'employee_uuid', 'users', 'uuid', 'employee_leave_balances_employee_uuid_fk', 'cascade');
        $this->safeForeign('employee_leave_balances', 'leave_type_uuid', 'leave_types', 'uuid', 'employee_leave_balances_leave_type_uuid_fk', 'cascade');

        $this->safeForeign('leave_ledger', 'company_uuid', 'companies', 'uuid', 'leave_ledger_company_uuid_fk', 'null');
        $this->safeForeign('leave_ledger', 'employee_uuid', 'users', 'uuid', 'leave_ledger_employee_uuid_fk', 'cascade');
        $this->safeForeign('leave_ledger', 'leave_type_uuid', 'leave_types', 'uuid', 'leave_ledger_leave_type_uuid_fk', 'cascade');
        $this->safeForeign('leave_ledger', 'policy_uuid', 'leave_policies', 'uuid', 'leave_ledger_policy_uuid_fk', 'null');
        $this->safeForeign('leave_ledger', 'created_by_uuid', 'users', 'uuid', 'leave_ledger_created_by_uuid_fk', 'null');
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
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }
};
