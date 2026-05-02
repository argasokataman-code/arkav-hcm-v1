<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $pairs = DB::select(
            'SELECT
                k.TABLE_NAME AS child_table,
                k.COLUMN_NAME AS child_column,
                k.REFERENCED_TABLE_NAME AS parent_table,
                rc.DELETE_RULE AS delete_rule,
                rc.UPDATE_RULE AS update_rule
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.TABLE_CONSTRAINTS tc
               ON tc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
              AND tc.TABLE_NAME = k.TABLE_NAME
              AND tc.CONSTRAINT_NAME = k.CONSTRAINT_NAME
             JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
               ON rc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
              AND rc.CONSTRAINT_NAME = k.CONSTRAINT_NAME
             WHERE k.CONSTRAINT_SCHEMA = DATABASE()
               AND tc.CONSTRAINT_TYPE = "FOREIGN KEY"
               AND k.REFERENCED_COLUMN_NAME = "id"
               AND k.COLUMN_NAME LIKE "%_id"
             ORDER BY k.TABLE_NAME, k.COLUMN_NAME'
        );

        $grouped = [];
        foreach ($pairs as $pair) {
            $grouped[$pair->child_table][] = $pair;
        }

        // 1) Ensure *_uuid columns exist.
        foreach ($grouped as $childTable => $items) {
            if (! Schema::hasTable($childTable)) {
                continue;
            }

            Schema::table($childTable, function (Blueprint $table) use ($childTable, $items): void {
                foreach ($items as $item) {
                    $uuidColumn = preg_replace('/_id$/', '_uuid', $item->child_column);
                    if ($uuidColumn === null || ! str_ends_with($item->child_column, '_id')) {
                        continue;
                    }

                    if (! Schema::hasColumn($childTable, $uuidColumn)) {
                        $table->uuid($uuidColumn)->nullable()->after($item->child_column);
                    }
                }
            });
        }

        // 2) Backfill *_uuid from parent.uuid using existing *_id relation.
        foreach ($pairs as $pair) {
            $childTable = $pair->child_table;
            $childColumn = $pair->child_column;
            $parentTable = $pair->parent_table;
            $uuidColumn = preg_replace('/_id$/', '_uuid', $childColumn);

            if ($uuidColumn === null || ! str_ends_with($childColumn, '_id')) {
                continue;
            }

            if (! Schema::hasTable($childTable)
                || ! Schema::hasTable($parentTable)
                || ! Schema::hasColumn($childTable, $childColumn)
                || ! Schema::hasColumn($childTable, $uuidColumn)
                || ! Schema::hasColumn($parentTable, 'id')
                || ! Schema::hasColumn($parentTable, 'uuid')) {
                continue;
            }

            DB::statement("\n                UPDATE `{$childTable}` c\n                JOIN `{$parentTable}` p ON p.`id` = c.`{$childColumn}`\n                SET c.`{$uuidColumn}` = p.`uuid`\n                WHERE c.`{$childColumn}` IS NOT NULL\n                  AND (c.`{$uuidColumn}` IS NULL OR c.`{$uuidColumn}` = '')\n            ");
        }

        // 3) Add index + FK to parent.uuid.
        foreach ($pairs as $pair) {
            $childTable = $pair->child_table;
            $childColumn = $pair->child_column;
            $parentTable = $pair->parent_table;
            $uuidColumn = preg_replace('/_id$/', '_uuid', $childColumn);

            if ($uuidColumn === null || ! str_ends_with($childColumn, '_id')) {
                continue;
            }

            if (! Schema::hasTable($childTable)
                || ! Schema::hasTable($parentTable)
                || ! Schema::hasColumn($childTable, $uuidColumn)
                || ! Schema::hasColumn($parentTable, 'uuid')) {
                continue;
            }

            $indexName = substr($childTable . '_' . $uuidColumn . '_idx', 0, 58) . '_' . substr(md5($childTable . $uuidColumn), 0, 5);
            $fkName = substr($childTable . '_' . $uuidColumn . '_fk', 0, 58) . '_' . substr(md5($childTable . $uuidColumn . $parentTable), 0, 5);

            // Parent uuid must be unique/indexed uniquely for InnoDB FK eligibility.
            if (! $this->hasUniqueColumn($parentTable, 'uuid')) {
                continue;
            }

            if (! $this->hasIndex($childTable, $indexName)) {
                Schema::table($childTable, function (Blueprint $table) use ($uuidColumn, $indexName): void {
                    $table->index($uuidColumn, $indexName);
                });
            }

            if (! $this->hasForeign($childTable, $fkName)) {
                $deleteRule = strtoupper((string) $pair->delete_rule);
                $updateRule = strtoupper((string) $pair->update_rule);

                Schema::table($childTable, function (Blueprint $table) use ($uuidColumn, $fkName, $parentTable, $deleteRule, $updateRule): void {
                    $foreign = $table->foreign($uuidColumn, $fkName)
                        ->references('uuid')
                        ->on($parentTable);

                    match ($deleteRule) {
                        'CASCADE' => $foreign->cascadeOnDelete(),
                        'SET NULL' => $foreign->nullOnDelete(),
                        'RESTRICT' => $foreign->restrictOnDelete(),
                        'NO ACTION' => $foreign->noActionOnDelete(),
                        default => null,
                    };

                    match ($updateRule) {
                        'CASCADE' => $foreign->cascadeOnUpdate(),
                        'SET NULL' => $foreign->nullOnUpdate(),
                        'RESTRICT' => $foreign->restrictOnUpdate(),
                        'NO ACTION' => $foreign->noActionOnUpdate(),
                        default => null,
                    };
                });
            }
        }
    }

    public function down(): void
    {
        // Intentionally no-op: backfill-only hardening migration.
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function hasForeign(string $table, string $constraintName): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_type', 'FOREIGN KEY')
            ->where('constraint_name', $constraintName)
            ->exists();
    }

    private function hasUniqueColumn(string $table, string $column): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->where('non_unique', 0)
            ->exists();
    }
};
