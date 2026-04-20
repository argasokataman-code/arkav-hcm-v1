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
            'hcm_roles',
            'hcm_permissions',
            'hcm_role_permissions',
            'hcm_user_role_audits',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                // Add company_uuid for hcm_roles if not exists
                if ($tableName === 'hcm_roles' && ! Schema::hasColumn($tableName, 'company_uuid')) {
                    $table->uuid('company_uuid')->nullable()->after('company_id');
                }

                // Add uuid columns for hcm_role_permissions
                if ($tableName === 'hcm_role_permissions') {
                    if (! Schema::hasColumn($tableName, 'role_uuid')) {
                        $table->uuid('role_uuid')->nullable()->after('role_id');
                    }
                    if (! Schema::hasColumn($tableName, 'permission_uuid')) {
                        $table->uuid('permission_uuid')->nullable()->after('permission_id');
                    }
                }

                // Add uuid columns for hcm_user_role_audits
                if ($tableName === 'hcm_user_role_audits') {
                    if (! Schema::hasColumn($tableName, 'company_uuid')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    }
                    if (! Schema::hasColumn($tableName, 'actor_user_uuid')) {
                        $table->uuid('actor_user_uuid')->nullable()->after('actor_user_id');
                    }
                    if (! Schema::hasColumn($tableName, 'target_user_uuid')) {
                        $table->uuid('target_user_uuid')->nullable()->after('target_user_id');
                    }
                    if (! Schema::hasColumn($tableName, 'role_uuid')) {
                        $table->uuid('role_uuid')->nullable()->after('role_id');
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
        $this->syncUuidByJoin('hcm_roles', 'company_id', 'company_uuid', 'companies');

        $this->syncUuidByJoin('hcm_role_permissions', 'role_id', 'role_uuid', 'hcm_roles');
        $this->syncUuidByJoin('hcm_role_permissions', 'permission_id', 'permission_uuid', 'hcm_permissions');

        $this->syncUuidByJoin('hcm_user_role_audits', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('hcm_user_role_audits', 'actor_user_id', 'actor_user_uuid', 'users');
        $this->syncUuidByJoin('hcm_user_role_audits', 'target_user_id', 'target_user_uuid', 'users');
        $this->syncUuidByJoin('hcm_user_role_audits', 'role_id', 'role_uuid', 'hcm_roles');
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
            ['hcm_roles', 'company_uuid', 'hcm_roles_company_uuid_idx'],
            ['hcm_role_permissions', 'role_uuid', 'hcm_role_permissions_role_uuid_idx'],
            ['hcm_role_permissions', 'permission_uuid', 'hcm_role_permissions_permission_uuid_idx'],
            ['hcm_user_role_audits', 'company_uuid', 'hcm_user_role_audits_company_uuid_idx'],
            ['hcm_user_role_audits', 'actor_user_uuid', 'hcm_user_role_audits_actor_user_uuid_idx'],
            ['hcm_user_role_audits', 'target_user_uuid', 'hcm_user_role_audits_target_user_uuid_idx'],
            ['hcm_user_role_audits', 'role_uuid', 'hcm_user_role_audits_role_uuid_idx'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            $this->safeIndex($table, $column, $name);
        }
    }

    private function addForeignKeys(): void
    {
        $this->safeForeign('hcm_roles', 'company_uuid', 'companies', 'uuid', 'hcm_roles_company_uuid_fk', 'null');

        $this->safeForeign('hcm_role_permissions', 'role_uuid', 'hcm_roles', 'uuid', 'hcm_role_permissions_role_uuid_fk', 'cascade');
        $this->safeForeign('hcm_role_permissions', 'permission_uuid', 'hcm_permissions', 'uuid', 'hcm_role_permissions_permission_uuid_fk', 'cascade');

        $this->safeForeign('hcm_user_role_audits', 'company_uuid', 'companies', 'uuid', 'hcm_user_role_audits_company_uuid_fk', 'null');
        $this->safeForeign('hcm_user_role_audits', 'actor_user_uuid', 'users', 'uuid', 'hcm_user_role_audits_actor_user_uuid_fk', 'null');
        $this->safeForeign('hcm_user_role_audits', 'target_user_uuid', 'users', 'uuid', 'hcm_user_role_audits_target_user_uuid_fk', 'cascade');
        $this->safeForeign('hcm_user_role_audits', 'role_uuid', 'hcm_roles', 'uuid', 'hcm_user_role_audits_role_uuid_fk', 'null');
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