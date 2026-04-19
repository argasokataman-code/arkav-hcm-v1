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
        $this->ensureColumns();
        $this->backfillData();
        $this->addIndexes();
    }

    public function down(): void
    {
        // Forward-only recovery migration.
    }

    private function ensureColumns(): void
    {
        if (Schema::hasTable('company_users')) {
            Schema::table('company_users', function (Blueprint $table): void {
                if (! Schema::hasColumn('company_users', 'company_uuid')) {
                    $table->uuid('company_uuid')->nullable()->after('company_id');
                }
                if (! Schema::hasColumn('company_users', 'user_uuid')) {
                    $table->uuid('user_uuid')->nullable()->after('user_id');
                }
                if (! Schema::hasColumn('company_users', 'uuid')) {
                    $table->uuid('uuid')->nullable()->after('id');
                }
            });
        }

        if (Schema::hasTable('hcm_user_roles')) {
            Schema::table('hcm_user_roles', function (Blueprint $table): void {
                if (! Schema::hasColumn('hcm_user_roles', 'company_uuid')) {
                    $table->uuid('company_uuid')->nullable()->after('company_id');
                }
                if (! Schema::hasColumn('hcm_user_roles', 'user_uuid')) {
                    $table->uuid('user_uuid')->nullable()->after('user_id');
                }
                if (! Schema::hasColumn('hcm_user_roles', 'uuid')) {
                    $table->uuid('uuid')->nullable()->after('id');
                }
            });
        }
    }

    private function backfillData(): void
    {
        $this->backfillRowUuid('company_users');
        $this->backfillRowUuid('hcm_user_roles');

        $this->updateFromTable('company_users', 'company_id', 'company_uuid', 'companies');
        $this->updateFromTable('company_users', 'user_id', 'user_uuid', 'users');
        $this->updateFromTable('hcm_user_roles', 'company_id', 'company_uuid', 'companies');
        $this->updateFromTable('hcm_user_roles', 'user_id', 'user_uuid', 'users');
    }

    private function addIndexes(): void
    {
        $this->safeIndex('company_users', 'company_uuid', 'company_users_company_uuid_idx');
        $this->safeIndex('company_users', 'user_uuid', 'company_users_user_uuid_idx');
        $this->safeUnique('company_users', 'uuid', 'company_users_uuid_unique');

        $this->safeIndex('hcm_user_roles', 'company_uuid', 'hcm_user_roles_company_uuid_idx');
        $this->safeIndex('hcm_user_roles', 'user_uuid', 'hcm_user_roles_user_uuid_idx');
        $this->safeUnique('hcm_user_roles', 'uuid', 'hcm_user_roles_uuid_unique');
    }

    private function backfillRowUuid(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'uuid')) {
            return;
        }

        DB::table($table)
            ->whereNull('uuid')
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            }, 'id');
    }

    private function updateFromTable(string $table, string $legacyIdColumn, string $uuidColumn, string $parentTable): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($parentTable)) {
            return;
        }

        if (! Schema::hasColumn($table, $legacyIdColumn) || ! Schema::hasColumn($table, $uuidColumn)) {
            return;
        }

        if (! Schema::hasColumn($parentTable, 'uuid')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE {$table} t JOIN {$parentTable} p ON t.{$legacyIdColumn} = p.id SET t.{$uuidColumn} = p.uuid WHERE t.{$legacyIdColumn} IS NOT NULL AND t.{$uuidColumn} IS NULL");

            return;
        }

        $rows = DB::table($table)
            ->whereNotNull($legacyIdColumn)
            ->whereNull($uuidColumn)
            ->select('id', $legacyIdColumn)
            ->get();

        foreach ($rows as $row) {
            $uuid = DB::table($parentTable)->where('id', $row->{$legacyIdColumn})->value('uuid');
            DB::table($table)->where('id', $row->id)->update([$uuidColumn => $uuid]);
        }
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

    private function safeUnique(string $table, string $column, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($column, $name): void {
                $tableBlueprint->unique($column, $name);
            });
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }
};
