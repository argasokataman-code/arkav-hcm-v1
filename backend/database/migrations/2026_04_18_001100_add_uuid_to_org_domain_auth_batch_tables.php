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
            'departments',
            'designations',
            'policies',
            'domains',
            'auth_tokens',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if (Schema::hasColumn($tableName, 'company_id') && ! Schema::hasColumn($tableName, 'company_uuid')) {
                    $table->uuid('company_uuid')->nullable()->after('company_id');
                }

                if (in_array($tableName, ['designations', 'policies'], true) && ! Schema::hasColumn($tableName, 'department_uuid')) {
                    $table->uuid('department_uuid')->nullable()->after('department_id');
                }

                if ($tableName === 'auth_tokens' && ! Schema::hasColumn($tableName, 'user_uuid')) {
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
        $this->syncUuidByJoin('designations', 'department_id', 'department_uuid', 'departments');
        $this->syncUuidByJoin('policies', 'department_id', 'department_uuid', 'departments');

        $this->syncUuidByJoin('domains', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('policies', 'company_id', 'company_uuid', 'companies');

        $this->syncUuidByJoin('auth_tokens', 'user_id', 'user_uuid', 'users');
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
            ['designations', 'department_uuid', 'designations_department_uuid_idx'],
            ['policies', 'department_uuid', 'policies_department_uuid_idx'],
            ['domains', 'company_uuid', 'domains_company_uuid_idx'],
            ['policies', 'company_uuid', 'policies_company_uuid_idx'],
            ['auth_tokens', 'user_uuid', 'auth_tokens_user_uuid_idx'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            $this->safeIndex($table, $column, $name);
        }
    }

    private function addForeignKeys(): void
    {
        $this->safeForeign('designations', 'department_uuid', 'departments', 'uuid', 'designations_department_uuid_fk', 'null');
        $this->safeForeign('policies', 'department_uuid', 'departments', 'uuid', 'policies_department_uuid_fk', 'null');
        $this->safeForeign('domains', 'company_uuid', 'companies', 'uuid', 'domains_company_uuid_fk', 'cascade');
        $this->safeForeign('policies', 'company_uuid', 'companies', 'uuid', 'policies_company_uuid_fk', 'null');
        $this->safeForeign('auth_tokens', 'user_uuid', 'users', 'uuid', 'auth_tokens_user_uuid_fk', 'cascade');
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
