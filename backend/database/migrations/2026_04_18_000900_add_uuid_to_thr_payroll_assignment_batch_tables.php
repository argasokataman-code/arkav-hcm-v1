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
            'hcm_thr_batches',
            'hcm_thr_batch_lines',
            'hcm_thr_disbursements',
            'hcm_employee_payroll_item_assignments',
            'hcm_manual_activities',
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

                if ($tableName === 'hcm_thr_batches') {
                    if (! Schema::hasColumn($tableName, 'hcm_thr_yearly_setting_uuid')) {
                        $table->uuid('hcm_thr_yearly_setting_uuid')->nullable()->after('hcm_thr_yearly_setting_id');
                    }
                    if (! Schema::hasColumn($tableName, 'assigned_by_user_uuid')) {
                        $table->uuid('assigned_by_user_uuid')->nullable()->after('assigned_by_user_id');
                    }
                    if (! Schema::hasColumn($tableName, 'hcm_payroll_period_uuid')) {
                        $table->uuid('hcm_payroll_period_uuid')->nullable()->after('hcm_payroll_period_id');
                    }
                    if (! Schema::hasColumn($tableName, 'hcm_payroll_run_uuid')) {
                        $table->uuid('hcm_payroll_run_uuid')->nullable()->after('hcm_payroll_run_id');
                    }
                    if (! Schema::hasColumn($tableName, 'generated_by_user_uuid')) {
                        $table->uuid('generated_by_user_uuid')->nullable()->after('generated_by_user_id');
                    }
                }

                if ($tableName === 'hcm_thr_batch_lines') {
                    if (! Schema::hasColumn($tableName, 'hcm_thr_batch_uuid')) {
                        $table->uuid('hcm_thr_batch_uuid')->nullable()->after('hcm_thr_batch_id');
                    }
                    if (! Schema::hasColumn($tableName, 'user_uuid')) {
                        $table->uuid('user_uuid')->nullable()->after('user_id');
                    }
                    if (! Schema::hasColumn($tableName, 'last_disbursement_uuid')) {
                        $table->uuid('last_disbursement_uuid')->nullable()->after('last_disbursement_id');
                    }
                }

                if ($tableName === 'hcm_thr_disbursements') {
                    if (! Schema::hasColumn($tableName, 'hcm_thr_batch_uuid')) {
                        $table->uuid('hcm_thr_batch_uuid')->nullable()->after('hcm_thr_batch_id');
                    }
                    if (! Schema::hasColumn($tableName, 'initiated_by_user_uuid')) {
                        $table->uuid('initiated_by_user_uuid')->nullable()->after('initiated_by_user_id');
                    }
                }

                if ($tableName === 'hcm_employee_payroll_item_assignments') {
                    if (! Schema::hasColumn($tableName, 'user_uuid')) {
                        $table->uuid('user_uuid')->nullable()->after('user_id');
                    }
                    if (! Schema::hasColumn($tableName, 'hcm_payroll_item_uuid')) {
                        $table->uuid('hcm_payroll_item_uuid')->nullable()->after('hcm_payroll_item_id');
                    }
                }

                if ($tableName === 'hcm_manual_activities') {
                    if (! Schema::hasColumn($tableName, 'created_by_user_uuid')) {
                        $table->uuid('created_by_user_uuid')->nullable()->after('created_by_user_id');
                    }
                    if (! Schema::hasColumn($tableName, 'updated_by_user_uuid')) {
                        $table->uuid('updated_by_user_uuid')->nullable()->after('updated_by_user_id');
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
        $this->syncUuidByJoin('hcm_thr_batches', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('hcm_thr_batches', 'hcm_thr_yearly_setting_id', 'hcm_thr_yearly_setting_uuid', 'hcm_thr_yearly_settings');
        $this->syncUuidByJoin('hcm_thr_batches', 'assigned_by_user_id', 'assigned_by_user_uuid', 'users');
        $this->syncUuidByJoin('hcm_thr_batches', 'hcm_payroll_period_id', 'hcm_payroll_period_uuid', 'hcm_payroll_periods');
        $this->syncUuidByJoin('hcm_thr_batches', 'hcm_payroll_run_id', 'hcm_payroll_run_uuid', 'hcm_payroll_runs');
        $this->syncUuidByJoin('hcm_thr_batches', 'generated_by_user_id', 'generated_by_user_uuid', 'users');

        $this->syncUuidByJoin('hcm_thr_batch_lines', 'hcm_thr_batch_id', 'hcm_thr_batch_uuid', 'hcm_thr_batches');
        $this->syncUuidByJoin('hcm_thr_batch_lines', 'user_id', 'user_uuid', 'users');
        $this->syncUuidByJoin('hcm_thr_batch_lines', 'last_disbursement_id', 'last_disbursement_uuid', 'hcm_thr_disbursements');

        $this->syncUuidByJoin('hcm_thr_disbursements', 'hcm_thr_batch_id', 'hcm_thr_batch_uuid', 'hcm_thr_batches');
        $this->syncUuidByJoin('hcm_thr_disbursements', 'initiated_by_user_id', 'initiated_by_user_uuid', 'users');

        $this->syncUuidByJoin('hcm_employee_payroll_item_assignments', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('hcm_employee_payroll_item_assignments', 'user_id', 'user_uuid', 'users');
        $this->syncUuidByJoin('hcm_employee_payroll_item_assignments', 'hcm_payroll_item_id', 'hcm_payroll_item_uuid', 'hcm_payroll_items');

        $this->syncUuidByJoin('hcm_manual_activities', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('hcm_manual_activities', 'created_by_user_id', 'created_by_user_uuid', 'users');
        $this->syncUuidByJoin('hcm_manual_activities', 'updated_by_user_id', 'updated_by_user_uuid', 'users');
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
            ['hcm_thr_batches', 'company_uuid', 'hcm_thr_batches_company_uuid_idx'],
            ['hcm_thr_batches', 'hcm_thr_yearly_setting_uuid', 'hcm_thr_batches_yearly_setting_uuid_idx'],
            ['hcm_thr_batches', 'assigned_by_user_uuid', 'hcm_thr_batches_assigned_by_user_uuid_idx'],
            ['hcm_thr_batches', 'hcm_payroll_period_uuid', 'hcm_thr_batches_payroll_period_uuid_idx'],
            ['hcm_thr_batches', 'hcm_payroll_run_uuid', 'hcm_thr_batches_payroll_run_uuid_idx'],
            ['hcm_thr_batches', 'generated_by_user_uuid', 'hcm_thr_batches_generated_by_user_uuid_idx'],
            ['hcm_thr_batch_lines', 'hcm_thr_batch_uuid', 'hcm_thr_batch_lines_batch_uuid_idx'],
            ['hcm_thr_batch_lines', 'user_uuid', 'hcm_thr_batch_lines_user_uuid_idx'],
            ['hcm_thr_batch_lines', 'last_disbursement_uuid', 'hcm_thr_batch_lines_last_disbursement_uuid_idx'],
            ['hcm_thr_disbursements', 'hcm_thr_batch_uuid', 'hcm_thr_disbursements_batch_uuid_idx'],
            ['hcm_thr_disbursements', 'initiated_by_user_uuid', 'hcm_thr_disbursements_initiated_by_user_uuid_idx'],
            ['hcm_employee_payroll_item_assignments', 'company_uuid', 'hcm_epia_company_uuid_idx'],
            ['hcm_employee_payroll_item_assignments', 'user_uuid', 'hcm_epia_user_uuid_idx'],
            ['hcm_employee_payroll_item_assignments', 'hcm_payroll_item_uuid', 'hcm_epia_item_uuid_idx'],
            ['hcm_manual_activities', 'company_uuid', 'hcm_manual_activities_company_uuid_idx'],
            ['hcm_manual_activities', 'created_by_user_uuid', 'hcm_manual_activities_created_by_user_uuid_idx'],
            ['hcm_manual_activities', 'updated_by_user_uuid', 'hcm_manual_activities_updated_by_user_uuid_idx'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            $this->safeIndex($table, $column, $name);
        }
    }

    private function addForeignKeys(): void
    {
        $this->safeForeign('hcm_thr_batches', 'company_uuid', 'companies', 'uuid', 'hcm_thr_batches_company_uuid_fk', 'null');
        $this->safeForeign('hcm_thr_batches', 'hcm_thr_yearly_setting_uuid', 'hcm_thr_yearly_settings', 'uuid', 'hcm_thr_batches_yearly_setting_uuid_fk', 'null');
        $this->safeForeign('hcm_thr_batches', 'assigned_by_user_uuid', 'users', 'uuid', 'hcm_thr_batches_assigned_by_user_uuid_fk', 'null');
        $this->safeForeign('hcm_thr_batches', 'hcm_payroll_period_uuid', 'hcm_payroll_periods', 'uuid', 'hcm_thr_batches_payroll_period_uuid_fk', 'null');
        $this->safeForeign('hcm_thr_batches', 'hcm_payroll_run_uuid', 'hcm_payroll_runs', 'uuid', 'hcm_thr_batches_payroll_run_uuid_fk', 'null');
        $this->safeForeign('hcm_thr_batches', 'generated_by_user_uuid', 'users', 'uuid', 'hcm_thr_batches_generated_by_user_uuid_fk', 'null');

        $this->safeForeign('hcm_thr_batch_lines', 'hcm_thr_batch_uuid', 'hcm_thr_batches', 'uuid', 'hcm_thr_batch_lines_batch_uuid_fk', 'cascade');
        $this->safeForeign('hcm_thr_batch_lines', 'user_uuid', 'users', 'uuid', 'hcm_thr_batch_lines_user_uuid_fk', 'cascade');
        $this->safeForeign('hcm_thr_batch_lines', 'last_disbursement_uuid', 'hcm_thr_disbursements', 'uuid', 'hcm_thr_batch_lines_last_disbursement_uuid_fk', 'null');

        $this->safeForeign('hcm_thr_disbursements', 'hcm_thr_batch_uuid', 'hcm_thr_batches', 'uuid', 'hcm_thr_disbursements_batch_uuid_fk', 'cascade');
        $this->safeForeign('hcm_thr_disbursements', 'initiated_by_user_uuid', 'users', 'uuid', 'hcm_thr_disbursements_initiated_by_user_uuid_fk', 'null');

        $this->safeForeign('hcm_employee_payroll_item_assignments', 'company_uuid', 'companies', 'uuid', 'hcm_epia_company_uuid_fk', 'null');
        $this->safeForeign('hcm_employee_payroll_item_assignments', 'user_uuid', 'users', 'uuid', 'hcm_epia_user_uuid_fk', 'cascade');
        $this->safeForeign('hcm_employee_payroll_item_assignments', 'hcm_payroll_item_uuid', 'hcm_payroll_items', 'uuid', 'hcm_epia_item_uuid_fk', 'cascade');

        $this->safeForeign('hcm_manual_activities', 'company_uuid', 'companies', 'uuid', 'hcm_manual_activities_company_uuid_fk', 'null');
        $this->safeForeign('hcm_manual_activities', 'created_by_user_uuid', 'users', 'uuid', 'hcm_manual_activities_created_by_user_uuid_fk', 'null');
        $this->safeForeign('hcm_manual_activities', 'updated_by_user_uuid', 'users', 'uuid', 'hcm_manual_activities_updated_by_user_uuid_fk', 'null');
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
