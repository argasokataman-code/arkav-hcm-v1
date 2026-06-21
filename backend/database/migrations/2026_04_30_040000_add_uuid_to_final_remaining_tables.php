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
            'dashboard_metrics', // sudah ada uuid
            'domain_verification_logs', // sudah ada uuid
            'leave_approval_workflow_steps', // sudah ada uuid
            'leave_approval_workflows', // belum ada uuid
            'leave_blackout_dates', // belum ada uuid
            'leave_request_attachments', // belum ada uuid
            'leave_request_audits', // belum ada uuid
            'leave_request_breakdowns', // belum ada uuid
            'wilayah_districts', // belum ada uuid
            'wilayah_regencies', // belum ada uuid
            'wilayah_villages', // belum ada uuid
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

                $table->uuid('uuid')->nullable()->unique()->after('id');

                // Add specific foreign key UUID columns
                if ($tableName === 'leave_approval_workflows') {
                    if (! Schema::hasColumn($tableName, 'company_uuid')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    }
                }

                if ($tableName === 'leave_blackout_dates') {
                    if (! Schema::hasColumn($tableName, 'company_uuid')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    }
                }

                if ($tableName === 'leave_request_attachments') {
                    if (! Schema::hasColumn($tableName, 'leave_request_uuid')) {
                        $table->uuid('leave_request_uuid')->nullable()->after('leave_request_id');
                    }
                }

                if ($tableName === 'leave_request_audits') {
                    if (! Schema::hasColumn($tableName, 'leave_request_uuid')) {
                        $table->uuid('leave_request_uuid')->nullable()->after('leave_request_id');
                    }
                }

                if ($tableName === 'leave_request_breakdowns') {
                    if (! Schema::hasColumn($tableName, 'leave_request_uuid')) {
                        $table->uuid('leave_request_uuid')->nullable()->after('leave_request_id');
                    }
                }

                if ($tableName === 'wilayah_districts') {
                    if (! Schema::hasColumn($tableName, 'regency_uuid')) {
                        $table->uuid('regency_uuid')->nullable()->after('regency_id');
                    }
                }

                if ($tableName === 'wilayah_regencies') {
                    if (! Schema::hasColumn($tableName, 'province_uuid')) {
                        $table->uuid('province_uuid')->nullable()->after('province_id');
                    }
                }

                if ($tableName === 'wilayah_villages') {
                    if (! Schema::hasColumn($tableName, 'district_uuid')) {
                        $table->uuid('district_uuid')->nullable()->after('district_id');
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
        $this->syncUuidByJoin('leave_approval_workflows', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('leave_blackout_dates', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('leave_request_attachments', 'leave_request_id', 'leave_request_uuid', 'leave_requests');
        $this->syncUuidByJoin('leave_request_audits', 'leave_request_id', 'leave_request_uuid', 'leave_requests');
        $this->syncUuidByJoin('leave_request_breakdowns', 'leave_request_id', 'leave_request_uuid', 'leave_requests');
        $this->syncUuidByJoin('wilayah_districts', 'regency_id', 'regency_uuid', 'wilayah_regencies');
        $this->syncUuidByJoin('wilayah_regencies', 'province_id', 'province_uuid', 'wilayah_provinces');
        $this->syncUuidByJoin('wilayah_villages', 'district_id', 'district_uuid', 'wilayah_districts');
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
            ['leave_approval_workflows', 'company_uuid', 'leave_approval_workflows_company_uuid_idx'],
            ['leave_blackout_dates', 'company_uuid', 'leave_blackout_dates_company_uuid_idx'],
            ['leave_request_attachments', 'leave_request_uuid', 'leave_request_attachments_leave_request_uuid_idx'],
            ['leave_request_audits', 'leave_request_uuid', 'leave_request_audits_leave_request_uuid_idx'],
            ['leave_request_breakdowns', 'leave_request_uuid', 'leave_request_breakdowns_leave_request_uuid_idx'],
            ['wilayah_districts', 'regency_uuid', 'wilayah_districts_regency_uuid_idx'],
            ['wilayah_regencies', 'province_uuid', 'wilayah_regencies_province_uuid_idx'],
            ['wilayah_villages', 'district_uuid', 'wilayah_villages_district_uuid_idx'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            $this->safeIndex($table, $column, $name);
        }
    }

    private function addForeignKeys(): void
    {
        $this->safeForeign('leave_approval_workflows', 'company_uuid', 'companies', 'uuid', 'leave_approval_workflows_company_uuid_fk', 'null');
        $this->safeForeign('leave_blackout_dates', 'company_uuid', 'companies', 'uuid', 'leave_blackout_dates_company_uuid_fk', 'null');
        $this->safeForeign('leave_request_attachments', 'leave_request_uuid', 'leave_requests', 'uuid', 'leave_request_attachments_leave_request_uuid_fk', 'cascade');
        $this->safeForeign('leave_request_audits', 'leave_request_uuid', 'leave_requests', 'uuid', 'leave_request_audits_leave_request_uuid_fk', 'cascade');
        $this->safeForeign('leave_request_breakdowns', 'leave_request_uuid', 'leave_requests', 'uuid', 'leave_request_breakdowns_leave_request_uuid_fk', 'cascade');
        $this->safeForeign('wilayah_districts', 'regency_uuid', 'wilayah_regencies', 'uuid', 'wilayah_districts_regency_uuid_fk', 'cascade');
        $this->safeForeign('wilayah_regencies', 'province_uuid', 'wilayah_provinces', 'uuid', 'wilayah_regencies_province_uuid_fk', 'cascade');
        $this->safeForeign('wilayah_villages', 'district_uuid', 'wilayah_districts', 'uuid', 'wilayah_villages_district_uuid_fk', 'cascade');
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
