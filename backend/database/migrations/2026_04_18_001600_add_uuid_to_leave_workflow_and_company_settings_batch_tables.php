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
            'company_settings',
            'hcm_leave_custom_policies',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if ($tableName === 'leave_approval_workflows') {
                    if (! Schema::hasColumn($tableName, 'company_uuid')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    }
                    if (! Schema::hasColumn($tableName, 'leave_type_uuid')) {
                        $table->uuid('leave_type_uuid')->nullable()->after('leave_type_id');
                    }
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

                if ($tableName === 'leave_blackout_dates') {
                    if (! Schema::hasColumn($tableName, 'company_uuid')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    }
                    if (! Schema::hasColumn($tableName, 'leave_type_uuid')) {
                        $table->uuid('leave_type_uuid')->nullable()->after('leave_type_id');
                    }
                }

                if ($tableName === 'company_settings' && ! Schema::hasColumn($tableName, 'company_uuid')) {
                    $table->uuid('company_uuid')->nullable()->after('company_id');
                }

                if ($tableName === 'hcm_leave_custom_policies') {
                    if (! Schema::hasColumn($tableName, 'leave_type_uuid')) {
                        if (Schema::hasColumn($tableName, 'leave_type_id')) {
                            $table->uuid('leave_type_uuid')->nullable()->after('leave_type_id');
                        } else {
                            $table->uuid('leave_type_uuid')->nullable();
                        }
                    }
                    if (! Schema::hasColumn($tableName, 'leave_policy_uuid')) {
                        if (Schema::hasColumn($tableName, 'leave_policy_id')) {
                            $table->uuid('leave_policy_uuid')->nullable()->after('leave_policy_id');
                        } else {
                            $table->uuid('leave_policy_uuid')->nullable();
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
        $this->syncUuidByJoin('leave_approval_workflows', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('leave_approval_workflows', 'leave_type_id', 'leave_type_uuid', 'leave_types');

        $this->syncUuidByJoin('leave_approval_workflow_steps', 'workflow_id', 'workflow_uuid', 'leave_approval_workflows');
        $this->syncUuidByJoin('leave_approval_workflow_steps', 'approver_user_id', 'approver_user_uuid', 'users');
        $this->syncUuidByJoin('leave_approval_workflow_steps', 'designation_id', 'designation_uuid', 'designations');

        $this->syncUuidByJoin('leave_blackout_dates', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('leave_blackout_dates', 'leave_type_id', 'leave_type_uuid', 'leave_types');

        $this->syncUuidByJoin('company_settings', 'company_id', 'company_uuid', 'companies');

        $this->syncUuidByJoin('hcm_leave_custom_policies', 'leave_type_id', 'leave_type_uuid', 'leave_types');
        $this->syncUuidByJoin('hcm_leave_custom_policies', 'leave_policy_id', 'leave_policy_uuid', 'leave_policies');
        $this->syncLeaveCustomPolicyTypeUuidFromCode();
    }

    private function syncLeaveCustomPolicyTypeUuidFromCode(): void
    {
        if (! Schema::hasTable('hcm_leave_custom_policies')) {
            return;
        }

        if (! Schema::hasColumn('hcm_leave_custom_policies', 'leave_type_code') || ! Schema::hasColumn('hcm_leave_custom_policies', 'leave_type_uuid')) {
            return;
        }

        if (! Schema::hasTable('leave_types') || ! Schema::hasColumn('leave_types', 'code') || ! Schema::hasColumn('leave_types', 'uuid')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('UPDATE hcm_leave_custom_policies p JOIN leave_types t ON p.leave_type_code = t.code SET p.leave_type_uuid = t.uuid WHERE p.leave_type_uuid IS NULL AND p.leave_type_code IS NOT NULL');

            return;
        }

        $rows = DB::table('hcm_leave_custom_policies')
            ->whereNull('leave_type_uuid')
            ->whereNotNull('leave_type_code')
            ->select('id', 'leave_type_code')
            ->get();

        foreach ($rows as $row) {
            $uuid = DB::table('leave_types')->where('code', $row->leave_type_code)->value('uuid');
            if ($uuid) {
                DB::table('hcm_leave_custom_policies')->where('id', $row->id)->update(['leave_type_uuid' => $uuid]);
            }
        }
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
            ['company_settings', 'company_uuid', 'company_settings_company_uuid_idx'],
            ['hcm_leave_custom_policies', 'leave_type_uuid', 'hcm_leave_custom_policies_leave_type_uuid_idx'],
            ['hcm_leave_custom_policies', 'leave_policy_uuid', 'hcm_leave_custom_policies_leave_policy_uuid_idx'],
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

        $this->safeForeign('company_settings', 'company_uuid', 'companies', 'uuid', 'company_settings_company_uuid_fk', 'cascade');

        $this->safeForeign('hcm_leave_custom_policies', 'leave_type_uuid', 'leave_types', 'uuid', 'hcm_leave_custom_policies_leave_type_uuid_fk', 'null');
        $this->safeForeign('hcm_leave_custom_policies', 'leave_policy_uuid', 'leave_policies', 'uuid', 'hcm_leave_custom_policies_leave_policy_uuid_fk', 'null');
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
