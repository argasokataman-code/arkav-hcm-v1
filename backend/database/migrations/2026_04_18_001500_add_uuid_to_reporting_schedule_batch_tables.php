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
            'report_snapshots',
            'report_data_blocks',
            'report_filters',
            'report_exports',
            'hcm_schedule_timings',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if ($tableName === 'report_snapshots') {
                    if (! Schema::hasColumn($tableName, 'company_uuid')) {
                        if (Schema::hasColumn($tableName, 'company_id')) {
                            $table->uuid('company_uuid')->nullable()->after('company_id');
                        } else {
                            $table->uuid('company_uuid')->nullable();
                        }
                    }
                    if (! Schema::hasColumn($tableName, 'generated_by_user_uuid')) {
                        if (Schema::hasColumn($tableName, 'generated_by_user_id')) {
                            $table->uuid('generated_by_user_uuid')->nullable()->after('generated_by_user_id');
                        } else {
                            $table->uuid('generated_by_user_uuid')->nullable();
                        }
                    }
                }

                if (in_array($tableName, ['report_data_blocks', 'report_filters', 'report_exports'], true) && ! Schema::hasColumn($tableName, 'snapshot_uuid')) {
                    if (Schema::hasColumn($tableName, 'snapshot_id')) {
                        $table->uuid('snapshot_uuid')->nullable()->after('snapshot_id');
                    } else {
                        $table->uuid('snapshot_uuid')->nullable();
                    }
                }

                if ($tableName === 'hcm_schedule_timings') {
                    if (! Schema::hasColumn($tableName, 'company_uuid')) {
                        if (Schema::hasColumn($tableName, 'company_id')) {
                            $table->uuid('company_uuid')->nullable()->after('company_id');
                        } else {
                            $table->uuid('company_uuid')->nullable();
                        }
                    }
                    if (! Schema::hasColumn($tableName, 'user_uuid')) {
                        if (Schema::hasColumn($tableName, 'user_id')) {
                            $table->uuid('user_uuid')->nullable()->after('user_id');
                        } else {
                            $table->uuid('user_uuid')->nullable();
                        }
                    }
                    if (! Schema::hasColumn($tableName, 'updated_by_user_uuid')) {
                        if (Schema::hasColumn($tableName, 'updated_by_user_id')) {
                            $table->uuid('updated_by_user_uuid')->nullable()->after('updated_by_user_id');
                        } else {
                            $table->uuid('updated_by_user_uuid')->nullable();
                        }
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
        $this->syncUuidByJoin('report_snapshots', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('report_snapshots', 'generated_by_user_id', 'generated_by_user_uuid', 'users');

        $this->syncUuidByJoin('report_data_blocks', 'snapshot_id', 'snapshot_uuid', 'report_snapshots');
        $this->syncUuidByJoin('report_filters', 'snapshot_id', 'snapshot_uuid', 'report_snapshots');
        $this->syncUuidByJoin('report_exports', 'snapshot_id', 'snapshot_uuid', 'report_snapshots');

        $this->syncUuidByJoin('hcm_schedule_timings', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('hcm_schedule_timings', 'user_id', 'user_uuid', 'users');
        $this->syncUuidByJoin('hcm_schedule_timings', 'updated_by_user_id', 'updated_by_user_uuid', 'users');
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
            ['report_snapshots', 'company_uuid', 'report_snapshots_company_uuid_idx'],
            ['report_snapshots', 'generated_by_user_uuid', 'report_snapshots_generated_by_user_uuid_idx'],
            ['report_data_blocks', 'snapshot_uuid', 'report_data_blocks_snapshot_uuid_idx'],
            ['report_filters', 'snapshot_uuid', 'report_filters_snapshot_uuid_idx'],
            ['report_exports', 'snapshot_uuid', 'report_exports_snapshot_uuid_idx'],
            ['hcm_schedule_timings', 'company_uuid', 'hcm_schedule_timings_company_uuid_idx'],
            ['hcm_schedule_timings', 'user_uuid', 'hcm_schedule_timings_user_uuid_idx'],
            ['hcm_schedule_timings', 'updated_by_user_uuid', 'hcm_schedule_timings_updated_by_user_uuid_idx'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            $this->safeIndex($table, $column, $name);
        }
    }

    private function addForeignKeys(): void
    {
        $this->safeForeign('report_snapshots', 'company_uuid', 'companies', 'uuid', 'report_snapshots_company_uuid_fk', 'null');
        $this->safeForeign('report_snapshots', 'generated_by_user_uuid', 'users', 'uuid', 'report_snapshots_generated_by_user_uuid_fk', 'null');
        $this->safeForeign('report_data_blocks', 'snapshot_uuid', 'report_snapshots', 'uuid', 'report_data_blocks_snapshot_uuid_fk', 'cascade');
        $this->safeForeign('report_filters', 'snapshot_uuid', 'report_snapshots', 'uuid', 'report_filters_snapshot_uuid_fk', 'cascade');
        $this->safeForeign('report_exports', 'snapshot_uuid', 'report_snapshots', 'uuid', 'report_exports_snapshot_uuid_fk', 'cascade');

        $this->safeForeign('hcm_schedule_timings', 'company_uuid', 'companies', 'uuid', 'hcm_schedule_timings_company_uuid_fk', 'null');
        $this->safeForeign('hcm_schedule_timings', 'user_uuid', 'users', 'uuid', 'hcm_schedule_timings_user_uuid_fk', 'cascade');
        $this->safeForeign('hcm_schedule_timings', 'updated_by_user_uuid', 'users', 'uuid', 'hcm_schedule_timings_updated_by_user_uuid_fk', 'null');
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
