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
            'holidays',
            'leave_requests',
            'leave_request_breakdowns',
            'leave_request_attachments',
            'leave_request_audits',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if ($tableName === 'leave_requests') {
                    if (! Schema::hasColumn($tableName, 'company_uuid')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    }
                    if (! Schema::hasColumn($tableName, 'user_uuid')) {
                        $table->uuid('user_uuid')->nullable()->after('user_id');
                    }
                }

                if ($tableName === 'leave_request_breakdowns') {
                    if (! Schema::hasColumn($tableName, 'leave_request_uuid')) {
                        $table->uuid('leave_request_uuid')->nullable()->after('leave_request_id');
                    }
                    if (Schema::hasColumn($tableName, 'holiday_calendar_id') && ! Schema::hasColumn($tableName, 'holiday_calendar_uuid')) {
                        $table->uuid('holiday_calendar_uuid')->nullable()->after('holiday_calendar_id');
                    }
                }

                if ($tableName === 'leave_request_attachments') {
                    if (! Schema::hasColumn($tableName, 'leave_request_uuid')) {
                        $table->uuid('leave_request_uuid')->nullable()->after('leave_request_id');
                    }
                    if (! Schema::hasColumn($tableName, 'uploaded_by_uuid')) {
                        $table->uuid('uploaded_by_uuid')->nullable()->after('uploaded_by');
                    }
                    if (! Schema::hasColumn($tableName, 'verified_by_uuid')) {
                        $table->uuid('verified_by_uuid')->nullable()->after('verified_by');
                    }
                }

                if ($tableName === 'leave_request_audits') {
                    if (! Schema::hasColumn($tableName, 'leave_request_uuid')) {
                        $table->uuid('leave_request_uuid')->nullable()->after('leave_request_id');
                    }
                    if (! Schema::hasColumn($tableName, 'actor_user_uuid')) {
                        $table->uuid('actor_user_uuid')->nullable()->after('actor_user_id');
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
        $this->syncUuidByJoin('leave_requests', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('leave_requests', 'user_id', 'user_uuid', 'users');

        $this->syncUuidByJoin('leave_request_breakdowns', 'leave_request_id', 'leave_request_uuid', 'leave_requests');
        $this->syncUuidByJoin('leave_request_breakdowns', 'holiday_calendar_id', 'holiday_calendar_uuid', 'holiday_calendars');

        $this->syncUuidByJoin('leave_request_attachments', 'leave_request_id', 'leave_request_uuid', 'leave_requests');
        $this->syncUuidByJoin('leave_request_attachments', 'uploaded_by', 'uploaded_by_uuid', 'users');
        $this->syncUuidByJoin('leave_request_attachments', 'verified_by', 'verified_by_uuid', 'users');

        $this->syncUuidByJoin('leave_request_audits', 'leave_request_id', 'leave_request_uuid', 'leave_requests');
        $this->syncUuidByJoin('leave_request_audits', 'actor_user_id', 'actor_user_uuid', 'users');
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
            ['leave_requests', 'company_uuid', 'leave_requests_company_uuid_idx'],
            ['leave_requests', 'user_uuid', 'leave_requests_user_uuid_idx'],
            ['leave_request_breakdowns', 'leave_request_uuid', 'leave_request_breakdowns_leave_request_uuid_idx'],
            ['leave_request_breakdowns', 'holiday_calendar_uuid', 'leave_request_breakdowns_holiday_calendar_uuid_idx'],
            ['leave_request_attachments', 'leave_request_uuid', 'leave_request_attachments_leave_request_uuid_idx'],
            ['leave_request_attachments', 'uploaded_by_uuid', 'leave_request_attachments_uploaded_by_uuid_idx'],
            ['leave_request_attachments', 'verified_by_uuid', 'leave_request_attachments_verified_by_uuid_idx'],
            ['leave_request_audits', 'leave_request_uuid', 'leave_request_audits_leave_request_uuid_idx'],
            ['leave_request_audits', 'actor_user_uuid', 'leave_request_audits_actor_user_uuid_idx'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            $this->safeIndex($table, $column, $name);
        }
    }

    private function addForeignKeys(): void
    {
        $this->safeForeign('leave_requests', 'company_uuid', 'companies', 'uuid', 'leave_requests_company_uuid_fk', 'null');
        $this->safeForeign('leave_requests', 'user_uuid', 'users', 'uuid', 'leave_requests_user_uuid_fk', 'cascade');

        $this->safeForeign('leave_request_breakdowns', 'leave_request_uuid', 'leave_requests', 'uuid', 'leave_request_breakdowns_leave_request_uuid_fk', 'cascade');
        $this->safeForeign('leave_request_breakdowns', 'holiday_calendar_uuid', 'holiday_calendars', 'uuid', 'leave_request_breakdowns_holiday_calendar_uuid_fk', 'null');

        $this->safeForeign('leave_request_attachments', 'leave_request_uuid', 'leave_requests', 'uuid', 'leave_request_attachments_leave_request_uuid_fk', 'cascade');
        $this->safeForeign('leave_request_attachments', 'uploaded_by_uuid', 'users', 'uuid', 'leave_request_attachments_uploaded_by_uuid_fk', 'null');
        $this->safeForeign('leave_request_attachments', 'verified_by_uuid', 'users', 'uuid', 'leave_request_attachments_verified_by_uuid_fk', 'null');

        $this->safeForeign('leave_request_audits', 'leave_request_uuid', 'leave_requests', 'uuid', 'leave_request_audits_leave_request_uuid_fk', 'cascade');
        $this->safeForeign('leave_request_audits', 'actor_user_uuid', 'users', 'uuid', 'leave_request_audits_actor_user_uuid_fk', 'null');
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
