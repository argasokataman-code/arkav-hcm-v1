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
            'asset_categories',
            'assets',
            'asset_assignments',
            'asset_logs',
            'asset_attachments',
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

                if ($tableName === 'assets') {
                    if (! Schema::hasColumn($tableName, 'asset_category_uuid')) {
                        $table->uuid('asset_category_uuid')->nullable()->after('asset_category_id');
                    }
                }

                if (in_array($tableName, ['asset_assignments', 'asset_logs', 'asset_attachments'], true)) {
                    if (! Schema::hasColumn($tableName, 'asset_uuid')) {
                        $table->uuid('asset_uuid')->nullable()->after('asset_id');
                    }
                }

                if ($tableName === 'asset_assignments' && ! Schema::hasColumn($tableName, 'employee_uuid')) {
                    $table->uuid('employee_uuid')->nullable()->after('employee_id');
                }

                if ($tableName === 'asset_logs' && ! Schema::hasColumn($tableName, 'performed_by_uuid')) {
                    $table->uuid('performed_by_uuid')->nullable()->after('performed_by');
                }

                if ($tableName === 'asset_attachments' && ! Schema::hasColumn($tableName, 'uploaded_by_uuid')) {
                    $table->uuid('uploaded_by_uuid')->nullable()->after('uploaded_by');
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
        $this->syncUuidByJoin('asset_categories', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('assets', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('assets', 'asset_category_id', 'asset_category_uuid', 'asset_categories');

        $this->syncUuidByJoin('asset_assignments', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('asset_assignments', 'asset_id', 'asset_uuid', 'assets');
        $this->syncUuidByJoin('asset_assignments', 'employee_id', 'employee_uuid', 'employee_profiles');

        $this->syncUuidByJoin('asset_logs', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('asset_logs', 'asset_id', 'asset_uuid', 'assets');
        $this->syncUuidByJoin('asset_logs', 'performed_by', 'performed_by_uuid', 'users');

        $this->syncUuidByJoin('asset_attachments', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('asset_attachments', 'asset_id', 'asset_uuid', 'assets');
        $this->syncUuidByJoin('asset_attachments', 'uploaded_by', 'uploaded_by_uuid', 'users');
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
            ['asset_categories', 'company_uuid', 'asset_categories_company_uuid_idx'],
            ['assets', 'company_uuid', 'assets_company_uuid_idx'],
            ['assets', 'asset_category_uuid', 'assets_asset_category_uuid_idx'],
            ['asset_assignments', 'company_uuid', 'asset_assignments_company_uuid_idx'],
            ['asset_assignments', 'asset_uuid', 'asset_assignments_asset_uuid_idx'],
            ['asset_assignments', 'employee_uuid', 'asset_assignments_employee_uuid_idx'],
            ['asset_logs', 'company_uuid', 'asset_logs_company_uuid_idx'],
            ['asset_logs', 'asset_uuid', 'asset_logs_asset_uuid_idx'],
            ['asset_logs', 'performed_by_uuid', 'asset_logs_performed_by_uuid_idx'],
            ['asset_attachments', 'company_uuid', 'asset_attachments_company_uuid_idx'],
            ['asset_attachments', 'asset_uuid', 'asset_attachments_asset_uuid_idx'],
            ['asset_attachments', 'uploaded_by_uuid', 'asset_attachments_uploaded_by_uuid_idx'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            $this->safeIndex($table, $column, $name);
        }
    }

    private function addForeignKeys(): void
    {
        $this->safeForeign('asset_categories', 'company_uuid', 'companies', 'uuid', 'asset_categories_company_uuid_fk', 'null');
        $this->safeForeign('assets', 'company_uuid', 'companies', 'uuid', 'assets_company_uuid_fk', 'null');
        $this->safeForeign('assets', 'asset_category_uuid', 'asset_categories', 'uuid', 'assets_asset_category_uuid_fk', 'null');

        $this->safeForeign('asset_assignments', 'company_uuid', 'companies', 'uuid', 'asset_assignments_company_uuid_fk', 'null');
        $this->safeForeign('asset_assignments', 'asset_uuid', 'assets', 'uuid', 'asset_assignments_asset_uuid_fk', 'null');
        $this->safeForeign('asset_assignments', 'employee_uuid', 'employee_profiles', 'uuid', 'asset_assignments_employee_uuid_fk', 'null');

        $this->safeForeign('asset_logs', 'company_uuid', 'companies', 'uuid', 'asset_logs_company_uuid_fk', 'null');
        $this->safeForeign('asset_logs', 'asset_uuid', 'assets', 'uuid', 'asset_logs_asset_uuid_fk', 'null');
        $this->safeForeign('asset_logs', 'performed_by_uuid', 'users', 'uuid', 'asset_logs_performed_by_uuid_fk', 'null');

        $this->safeForeign('asset_attachments', 'company_uuid', 'companies', 'uuid', 'asset_attachments_company_uuid_fk', 'null');
        $this->safeForeign('asset_attachments', 'asset_uuid', 'assets', 'uuid', 'asset_attachments_asset_uuid_fk', 'null');
        $this->safeForeign('asset_attachments', 'uploaded_by_uuid', 'users', 'uuid', 'asset_attachments_uploaded_by_uuid_fk', 'null');
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
