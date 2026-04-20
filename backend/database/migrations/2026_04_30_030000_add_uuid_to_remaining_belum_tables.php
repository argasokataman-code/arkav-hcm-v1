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
            'hcm_overtime_types',
            'hcm_promotions',
            'hcm_shifts',
            'hcm_employee_payroll_item_assignments',
            'job_batches',
            'jobs',
            'cache',
            'cache_locks',
            'company_settings',
            'migrations',
            'settings',
            'wilayah_provinces',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                // Skip tables that already have uuid
                if (Schema::hasColumn($tableName, 'uuid')) {
                    return;
                }

                // Handle tables without 'id' column (cache, cache_locks)
                if (in_array($tableName, ['cache', 'cache_locks'])) {
                    $table->uuid('uuid')->nullable()->unique()->first();
                } else {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                // Add specific foreign key UUID columns
                if ($tableName === 'hcm_overtime_types') {
                    if (! Schema::hasColumn($tableName, 'company_uuid')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    }
                }

                if ($tableName === 'hcm_promotions') {
                    if (! Schema::hasColumn($tableName, 'company_uuid')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    }
                    if (! Schema::hasColumn($tableName, 'user_uuid')) {
                        $table->uuid('user_uuid')->nullable()->after('user_id');
                    }
                }

                if ($tableName === 'hcm_shifts') {
                    if (! Schema::hasColumn($tableName, 'company_uuid')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    }
                }

                if ($tableName === 'hcm_employee_payroll_item_assignments') {
                    if (! Schema::hasColumn($tableName, 'company_uuid')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    }
                    if (! Schema::hasColumn($tableName, 'user_uuid')) {
                        $table->uuid('user_uuid')->nullable()->after('user_id');
                    }
                    if (! Schema::hasColumn($tableName, 'payroll_item_uuid')) {
                        $table->uuid('payroll_item_uuid')->nullable()->after('hcm_payroll_item_id');
                    }
                }

                if ($tableName === 'company_settings') {
                    if (! Schema::hasColumn($tableName, 'company_uuid')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
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
        // Forward-only migration.
    }

    private function backfillRowUuids(array $tables): void
    {
        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'uuid')) {
                continue;
            }

            // Skip tables that already have uuid populated
            $hasUuidData = DB::table($tableName)->whereNotNull('uuid')->exists();
            if ($hasUuidData) {
                continue;
            }

            // Handle tables without 'id' column (cache, cache_locks)
            if (in_array($tableName, ['cache', 'cache_locks'])) {
                // For cache tables, use the primary key column
                $primaryKey = $tableName === 'cache' ? 'key' : 'key';
                DB::table($tableName)
                    ->whereNull('uuid')
                    ->orderBy($primaryKey)
                    ->chunkById(500, function ($rows) use ($tableName, $primaryKey): void {
                        foreach ($rows as $row) {
                            DB::table($tableName)->where($primaryKey, $row->{$primaryKey})->update(['uuid' => (string) Str::uuid()]);
                        }
                    }, $primaryKey);
            } else {
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
    }

    private function backfillForeignUuids(): void
    {
        $this->syncUuidByJoin('hcm_overtime_types', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('hcm_promotions', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('hcm_promotions', 'user_id', 'user_uuid', 'users');
        $this->syncUuidByJoin('hcm_shifts', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('hcm_employee_payroll_item_assignments', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('hcm_employee_payroll_item_assignments', 'user_id', 'user_uuid', 'users');
        $this->syncUuidByJoin('hcm_employee_payroll_item_assignments', 'hcm_payroll_item_id', 'payroll_item_uuid', 'hcm_payroll_items');
        $this->syncUuidByJoin('company_settings', 'company_id', 'company_uuid', 'companies');
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
            ['hcm_overtime_types', 'company_uuid', 'hcm_overtime_types_company_uuid_idx'],
            ['hcm_promotions', 'company_uuid', 'hcm_promotions_company_uuid_idx'],
            ['hcm_promotions', 'user_uuid', 'hcm_promotions_user_uuid_idx'],
            ['hcm_shifts', 'company_uuid', 'hcm_shifts_company_uuid_idx'],
            ['hcm_employee_payroll_item_assignments', 'company_uuid', 'hcm_employee_payroll_item_assignments_company_uuid_idx'],
            ['hcm_employee_payroll_item_assignments', 'user_uuid', 'hcm_employee_payroll_item_assignments_user_uuid_idx'],
            ['hcm_employee_payroll_item_assignments', 'payroll_item_uuid', 'hcm_employee_payroll_item_assignments_payroll_item_uuid_idx'],
            ['company_settings', 'company_uuid', 'company_settings_company_uuid_idx'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            $this->safeIndex($table, $column, $name);
        }
    }

    private function addForeignKeys(): void
    {
        $this->safeForeign('hcm_overtime_types', 'company_uuid', 'companies', 'uuid', 'hcm_overtime_types_company_uuid_fk', 'null');
        $this->safeForeign('hcm_promotions', 'company_uuid', 'companies', 'uuid', 'hcm_promotions_company_uuid_fk', 'null');
        $this->safeForeign('hcm_promotions', 'user_uuid', 'users', 'uuid', 'hcm_promotions_user_uuid_fk', 'cascade');
        $this->safeForeign('hcm_shifts', 'company_uuid', 'companies', 'uuid', 'hcm_shifts_company_uuid_fk', 'null');
        $this->safeForeign('hcm_employee_payroll_item_assignments', 'company_uuid', 'companies', 'uuid', 'hcm_employee_payroll_item_assignments_company_uuid_fk', 'null');
        $this->safeForeign('hcm_employee_payroll_item_assignments', 'user_uuid', 'users', 'uuid', 'hcm_employee_payroll_item_assignments_user_uuid_fk', 'cascade');
        $this->safeForeign('hcm_employee_payroll_item_assignments', 'payroll_item_uuid', 'hcm_payroll_items', 'uuid', 'hcm_employee_payroll_item_assignments_payroll_item_uuid_fk', 'cascade');
        $this->safeForeign('company_settings', 'company_uuid', 'companies', 'uuid', 'company_settings_company_uuid_fk', 'cascade');
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