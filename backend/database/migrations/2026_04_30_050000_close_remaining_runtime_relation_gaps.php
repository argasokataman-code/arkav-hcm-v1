<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $companyScopedTables = [
            ['hcm_role_permissions', 'company_uuid', 'hcm_role_permissions_company_uuid_idx', 'hcm_role_permissions_company_uuid_fk'],
            ['tickets', 'company_uuid', 'tickets_company_uuid_idx', 'tickets_company_uuid_fk'],
            ['hcm_trainers', 'company_uuid', 'hcm_trainers_company_uuid_idx', 'hcm_trainers_company_uuid_fk'],
            ['hcm_training_types', 'company_uuid', 'hcm_training_types_company_uuid_idx', 'hcm_training_types_company_uuid_fk'],
            ['hcm_trainings', 'company_uuid', 'hcm_trainings_company_uuid_idx', 'hcm_trainings_company_uuid_fk'],
        ];

        foreach ($companyScopedTables as [$tableName, $uuidColumn, $indexName, $foreignName]) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }

            if (! Schema::hasColumn($tableName, $uuidColumn)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName, $uuidColumn): void {
                    if (Schema::hasColumn($tableName, 'company_id')) {
                        $table->uuid($uuidColumn)->nullable()->after('company_id');

                        return;
                    }

                    $table->uuid($uuidColumn)->nullable();
                });
            }

            $this->syncUuidByJoin($tableName, 'company_id', $uuidColumn, 'companies');
            $this->safeIndex($tableName, $uuidColumn, $indexName);
            $this->safeForeign($tableName, $uuidColumn, 'companies', 'uuid', $foreignName, 'null');
        }

        if (Schema::hasTable('hcm_terminations') && Schema::hasColumn('hcm_terminations', 'settlement_payroll_period_id')) {
            $this->safeForeign(
                'hcm_terminations',
                'settlement_payroll_period_id',
                'hcm_payroll_periods',
                'id',
                'hcm_terminations_settlement_payroll_period_id_fk',
                'null'
            );
        }
    }

    public function down(): void
    {
        // Forward-only migration.
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

        $rows = DB::table($table)
            ->whereNotNull($legacyColumn)
            ->whereNull($uuidColumn)
            ->select('id', $legacyColumn)
            ->get();

        foreach ($rows as $row) {
            $uuid = DB::table($parentTable)->where('id', $row->{$legacyColumn})->value('uuid');
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
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false && stripos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }
    }
};