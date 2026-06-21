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
            'ticket_categories',
            'tickets',
            'ticket_comments',
            'ticket_attachments',
            'ticket_assignment_histories',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if ($tableName === 'tickets') {
                    if (! Schema::hasColumn($tableName, 'user_uuid')) {
                        $table->uuid('user_uuid')->nullable()->after('user_id');
                    }
                    if (! Schema::hasColumn($tableName, 'assignee_user_uuid')) {
                        $table->uuid('assignee_user_uuid')->nullable()->after('assignee_user_id');
                    }
                    if (! Schema::hasColumn($tableName, 'resolver_user_uuid')) {
                        $table->uuid('resolver_user_uuid')->nullable()->after('resolver_user_id');
                    }
                }

                if (in_array($tableName, ['ticket_comments', 'ticket_attachments', 'ticket_assignment_histories'], true)) {
                    if (! Schema::hasColumn($tableName, 'ticket_uuid')) {
                        $table->uuid('ticket_uuid')->nullable()->after('ticket_id');
                    }
                }

                if (in_array($tableName, ['ticket_comments', 'ticket_attachments'], true)) {
                    if (! Schema::hasColumn($tableName, 'user_uuid')) {
                        $table->uuid('user_uuid')->nullable()->after('user_id');
                    }
                }

                if ($tableName === 'ticket_assignment_histories') {
                    if (! Schema::hasColumn($tableName, 'actor_user_uuid')) {
                        $table->uuid('actor_user_uuid')->nullable()->after('actor_user_id');
                    }
                    if (! Schema::hasColumn($tableName, 'from_assignee_user_uuid')) {
                        $table->uuid('from_assignee_user_uuid')->nullable()->after('from_assignee_user_id');
                    }
                    if (! Schema::hasColumn($tableName, 'to_assignee_user_uuid')) {
                        $table->uuid('to_assignee_user_uuid')->nullable()->after('to_assignee_user_id');
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
        $this->syncUuidByJoin('tickets', 'user_id', 'user_uuid', 'users');
        $this->syncUuidByJoin('tickets', 'assignee_user_id', 'assignee_user_uuid', 'users');
        $this->syncUuidByJoin('tickets', 'resolver_user_id', 'resolver_user_uuid', 'users');

        $this->syncUuidByJoin('ticket_comments', 'ticket_id', 'ticket_uuid', 'tickets');
        $this->syncUuidByJoin('ticket_comments', 'user_id', 'user_uuid', 'users');

        $this->syncUuidByJoin('ticket_attachments', 'ticket_id', 'ticket_uuid', 'tickets');
        $this->syncUuidByJoin('ticket_attachments', 'user_id', 'user_uuid', 'users');

        $this->syncUuidByJoin('ticket_assignment_histories', 'ticket_id', 'ticket_uuid', 'tickets');
        $this->syncUuidByJoin('ticket_assignment_histories', 'actor_user_id', 'actor_user_uuid', 'users');
        $this->syncUuidByJoin('ticket_assignment_histories', 'from_assignee_user_id', 'from_assignee_user_uuid', 'users');
        $this->syncUuidByJoin('ticket_assignment_histories', 'to_assignee_user_id', 'to_assignee_user_uuid', 'users');
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
            ['tickets', 'user_uuid', 'tickets_user_uuid_idx'],
            ['tickets', 'assignee_user_uuid', 'tickets_assignee_user_uuid_idx'],
            ['tickets', 'resolver_user_uuid', 'tickets_resolver_user_uuid_idx'],
            ['ticket_comments', 'ticket_uuid', 'ticket_comments_ticket_uuid_idx'],
            ['ticket_comments', 'user_uuid', 'ticket_comments_user_uuid_idx'],
            ['ticket_attachments', 'ticket_uuid', 'ticket_attachments_ticket_uuid_idx'],
            ['ticket_attachments', 'user_uuid', 'ticket_attachments_user_uuid_idx'],
            ['ticket_assignment_histories', 'ticket_uuid', 'ticket_assignment_histories_ticket_uuid_idx'],
            ['ticket_assignment_histories', 'actor_user_uuid', 'ticket_assignment_histories_actor_user_uuid_idx'],
            ['ticket_assignment_histories', 'from_assignee_user_uuid', 'ticket_assignment_histories_from_assignee_user_uuid_idx'],
            ['ticket_assignment_histories', 'to_assignee_user_uuid', 'ticket_assignment_histories_to_assignee_user_uuid_idx'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            $this->safeIndex($table, $column, $name);
        }
    }

    private function addForeignKeys(): void
    {
        $this->safeForeign('tickets', 'user_uuid', 'users', 'uuid', 'tickets_user_uuid_fk', 'cascade');
        $this->safeForeign('tickets', 'assignee_user_uuid', 'users', 'uuid', 'tickets_assignee_user_uuid_fk', 'null');
        $this->safeForeign('tickets', 'resolver_user_uuid', 'users', 'uuid', 'tickets_resolver_user_uuid_fk', 'null');

        $this->safeForeign('ticket_comments', 'ticket_uuid', 'tickets', 'uuid', 'ticket_comments_ticket_uuid_fk', 'cascade');
        $this->safeForeign('ticket_comments', 'user_uuid', 'users', 'uuid', 'ticket_comments_user_uuid_fk', 'cascade');

        $this->safeForeign('ticket_attachments', 'ticket_uuid', 'tickets', 'uuid', 'ticket_attachments_ticket_uuid_fk', 'cascade');
        $this->safeForeign('ticket_attachments', 'user_uuid', 'users', 'uuid', 'ticket_attachments_user_uuid_fk', 'cascade');

        $this->safeForeign('ticket_assignment_histories', 'ticket_uuid', 'tickets', 'uuid', 'ticket_assignment_histories_ticket_uuid_fk', 'cascade');
        $this->safeForeign('ticket_assignment_histories', 'actor_user_uuid', 'users', 'uuid', 'ticket_assignment_histories_actor_user_uuid_fk', 'cascade');
        $this->safeForeign('ticket_assignment_histories', 'from_assignee_user_uuid', 'users', 'uuid', 'ticket_assignment_histories_from_assignee_user_uuid_fk', 'null');
        $this->safeForeign('ticket_assignment_histories', 'to_assignee_user_uuid', 'users', 'uuid', 'ticket_assignment_histories_to_assignee_user_uuid_fk', 'null');
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
