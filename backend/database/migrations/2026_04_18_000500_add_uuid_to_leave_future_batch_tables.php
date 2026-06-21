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
            'leave_approval_workflows',
            'leave_approval_workflow_steps',
            'leave_blackout_dates',
            'holiday_calendars',
            'leave_approvals',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if (in_array($tableName, ['leave_approval_workflows', 'leave_blackout_dates', 'holiday_calendars', 'leave_approvals'], true) && ! Schema::hasColumn($tableName, 'company_uuid')) {
                    $table->uuid('company_uuid')->nullable()->after('company_id');
                }

                if (in_array($tableName, ['leave_approval_workflows', 'leave_blackout_dates'], true) && ! Schema::hasColumn($tableName, 'leave_type_uuid')) {
                    $table->uuid('leave_type_uuid')->nullable()->after('leave_type_id');
                }

                if ($tableName === 'leave_approval_workflow_steps') {
                    if (! Schema::hasColumn($tableName, 'workflow_uuid')) {
                        $table->uuid('workflow_uuid')->nullable()->after('workflow_id');
                    }
                    if (! Schema::hasColumn($tableName, 'approver_user_uuid')) {
                        $table->uuid('approver_user_uuid')->nullable()->after('approver_user_id');
                    }
                    if (! Schema::hasColumn($tableName, 'designation_uuid')) {
                        $table->uuid('designation_uuid')->nullable()->after('designation_id');
                    }
                }

                if ($tableName === 'holiday_calendars' && Schema::hasColumn($tableName, 'holiday_id') && ! Schema::hasColumn($tableName, 'holiday_uuid')) {
                    $table->uuid('holiday_uuid')->nullable()->after('holiday_id');
                }

                if ($tableName === 'leave_approvals') {
                    if (! Schema::hasColumn($tableName, 'leave_request_uuid')) {
                        $table->uuid('leave_request_uuid')->nullable()->after('leave_request_id');
                    }
                    if (! Schema::hasColumn($tableName, 'approver_uuid')) {
                        $table->uuid('approver_uuid')->nullable()->after('approver_id');
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
        $this->syncUuidByJoin('leave_approval_workflows', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('leave_approval_workflows', 'leave_type_id', 'leave_type_uuid', 'leave_types');

        $this->syncUuidByJoin('leave_approval_workflow_steps', 'workflow_id', 'workflow_uuid', 'leave_approval_workflows');
        $this->syncUuidByJoin('leave_approval_workflow_steps', 'approver_user_id', 'approver_user_uuid', 'users');
        $this->syncUuidByJoin('leave_approval_workflow_steps', 'designation_id', 'designation_uuid', 'designations');

        $this->syncUuidByJoin('leave_blackout_dates', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('leave_blackout_dates', 'leave_type_id', 'leave_type_uuid', 'leave_types');

        $this->syncUuidByJoin('holiday_calendars', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('holiday_calendars', 'holiday_id', 'holiday_uuid', 'holidays');

        $this->syncUuidByJoin('leave_approvals', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('leave_approvals', 'leave_request_id', 'leave_request_uuid', 'leave_requests');
        $this->syncUuidByJoin('leave_approvals', 'approver_id', 'approver_uuid', 'users');
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
            ['leave_approval_workflows', 'leave_type_uuid', 'leave_approval_workflows_leave_type_uuid_idx'],
            ['leave_approval_workflow_steps', 'workflow_uuid', 'leave_approval_workflow_steps_workflow_uuid_idx'],
            ['leave_approval_workflow_steps', 'approver_user_uuid', 'leave_approval_workflow_steps_approver_user_uuid_idx'],
            ['leave_approval_workflow_steps', 'designation_uuid', 'leave_approval_workflow_steps_designation_uuid_idx'],
            ['leave_blackout_dates', 'company_uuid', 'leave_blackout_dates_company_uuid_idx'],
            ['leave_blackout_dates', 'leave_type_uuid', 'leave_blackout_dates_leave_type_uuid_idx'],
            ['holiday_calendars', 'company_uuid', 'holiday_calendars_company_uuid_idx'],
            ['holiday_calendars', 'holiday_uuid', 'holiday_calendars_holiday_uuid_idx'],
            ['leave_approvals', 'company_uuid', 'leave_approvals_company_uuid_idx'],
            ['leave_approvals', 'leave_request_uuid', 'leave_approvals_leave_request_uuid_idx'],
            ['leave_approvals', 'approver_uuid', 'leave_approvals_approver_uuid_idx'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            $this->safeIndex($table, $column, $name);
        }
    }

    private function addForeignKeys(): void
    {
        $this->safeForeign('leave_approval_workflows', 'company_uuid', 'companies', 'uuid', 'leave_approval_workflows_company_uuid_fk', 'null');
        $this->safeForeign('leave_approval_workflows', 'leave_type_uuid', 'leave_types', 'uuid', 'leave_approval_workflows_leave_type_uuid_fk', 'null');

        $this->safeForeign('leave_approval_workflow_steps', 'workflow_uuid', 'leave_approval_workflows', 'uuid', 'leave_approval_workflow_steps_workflow_uuid_fk', 'cascade');
        $this->safeForeign('leave_approval_workflow_steps', 'approver_user_uuid', 'users', 'uuid', 'leave_approval_workflow_steps_approver_user_uuid_fk', 'null');
        $this->safeForeign('leave_approval_workflow_steps', 'designation_uuid', 'designations', 'uuid', 'leave_approval_workflow_steps_designation_uuid_fk', 'null');

        $this->safeForeign('leave_blackout_dates', 'company_uuid', 'companies', 'uuid', 'leave_blackout_dates_company_uuid_fk', 'null');
        $this->safeForeign('leave_blackout_dates', 'leave_type_uuid', 'leave_types', 'uuid', 'leave_blackout_dates_leave_type_uuid_fk', 'null');

        $this->safeForeign('holiday_calendars', 'company_uuid', 'companies', 'uuid', 'holiday_calendars_company_uuid_fk', 'null');
        $this->safeForeign('holiday_calendars', 'holiday_uuid', 'holidays', 'uuid', 'holiday_calendars_holiday_uuid_fk', 'null');

        $this->safeForeign('leave_approvals', 'company_uuid', 'companies', 'uuid', 'leave_approvals_company_uuid_fk', 'null');
        $this->safeForeign('leave_approvals', 'leave_request_uuid', 'leave_requests', 'uuid', 'leave_approvals_leave_request_uuid_fk', 'cascade');
        $this->safeForeign('leave_approvals', 'approver_uuid', 'users', 'uuid', 'leave_approvals_approver_uuid_fk', 'cascade');
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
