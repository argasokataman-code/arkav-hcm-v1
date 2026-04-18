<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Core tables selected for final UUID cutover.
     */
    private array $coreTables = [
        'users',
        'companies',
        'employee_profiles',
        'hcm_user_roles',
        'company_users',
    ];

    public function up(): void
    {
        if (! $this->supportsPrimaryKeyCutover()) {
            return;
        }

        $this->migrateInboundForeignKeysToUuid();

        foreach ($this->coreTables as $table) {
            $this->finalizePrimaryKeyCutover($table);
        }
    }

    public function down(): void
    {
        // Forward-only migration.
    }

    private function supportsPrimaryKeyCutover(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function migrateInboundForeignKeysToUuid(): void
    {
        foreach ($this->coreTables as $parentTable) {
            $relations = $this->getInboundForeignKeyRelations($parentTable, 'id');

            foreach ($relations as $relation) {
                $this->migrateInboundForeignKeyToUuid($relation);
            }
        }
    }

    private function finalizePrimaryKeyCutover(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id') || ! Schema::hasColumn($table, 'uuid')) {
            return;
        }

        if ($this->getPrimaryKeyColumn($table) === 'uuid') {
            return;
        }

        $uuidRelations = $this->getInboundForeignKeyRelations($table, 'uuid');

        foreach ($uuidRelations as $relation) {
            $this->dropForeignKeyIfExists((string) $relation->table_name, (string) $relation->constraint_name);
        }

        if ($this->hasInboundForeignKeyReferences($table, 'id')) {
            throw new RuntimeException("Cannot switch {$table} primary key to uuid: inbound id foreign keys still exist");
        }

        $this->backfillMissingUuids($table);
        $this->assertNoNullUuids($table);
        $this->assertNoDuplicateUuids($table);

        $this->ensureIndex($table, 'id', $this->legacyIdIndexName($table));
        $this->ensureUniqueIndex($table, 'uuid', $this->uuidUniqueIndexName($table));

        DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY, ADD PRIMARY KEY (`uuid`)");

        foreach ($uuidRelations as $relation) {
            $this->addForeignKey(
                (string) $relation->table_name,
                (string) $relation->column_name,
                (string) $relation->referenced_table_name,
                (string) $relation->referenced_column_name,
                strtoupper((string) $relation->delete_rule)
            );
        }
    }

    private function migrateInboundForeignKeyToUuid(object $relation): void
    {
        $childTable = (string) $relation->table_name;
        $childColumn = (string) $relation->column_name;
        $parentTable = (string) $relation->referenced_table_name;
        $parentColumn = (string) $relation->referenced_column_name;
        $deleteRule = strtoupper((string) $relation->delete_rule);
        $uuidColumn = $this->resolveUuidColumnName($childTable, $childColumn, $parentTable);

        if (! Schema::hasTable($childTable) || ! Schema::hasTable($parentTable)) {
            return;
        }

        if (! Schema::hasColumn($childTable, $childColumn) || ! Schema::hasColumn($parentTable, 'uuid')) {
            return;
        }

        if (! Schema::hasColumn($childTable, $uuidColumn)) {
            Schema::table($childTable, function (Blueprint $blueprint) use ($childColumn, $uuidColumn): void {
                $blueprint->uuid($uuidColumn)->nullable()->after($childColumn);
            });
        }

        $this->backfillUuidForeignKey($childTable, $childColumn, $uuidColumn, $parentTable, $parentColumn);

        if (! $this->foreignKeyReferences($childTable, $uuidColumn, $parentTable, 'uuid')) {
            $this->addForeignKey($childTable, $uuidColumn, $parentTable, 'uuid', $deleteRule);
        }

        $this->dropForeignKeyIfExists($childTable, (string) $relation->constraint_name);
    }

    private function switchPrimaryKeyToUuid(string $table): void
    {
        $this->finalizePrimaryKeyCutover($table);
    }

    private function backfillUuidForeignKey(string $childTable, string $childColumn, string $uuidColumn, string $parentTable, string $parentColumn): void
    {
        DB::statement(
            "UPDATE `{$childTable}` AS child INNER JOIN `{$parentTable}` AS parent ON child.`{$childColumn}` = parent.`{$parentColumn}` SET child.`{$uuidColumn}` = parent.`uuid` WHERE child.`{$uuidColumn}` IS NULL AND child.`{$childColumn}` IS NOT NULL"
        );
    }

    private function addForeignKey(string $childTable, string $childColumn, string $parentTable, string $parentColumn, string $deleteRule): void
    {
        $constraintName = $this->fkName($childTable, $childColumn);

        Schema::table($childTable, function (Blueprint $blueprint) use ($childColumn, $parentTable, $parentColumn, $constraintName, $deleteRule): void {
            $foreign = $blueprint->foreign($childColumn, $constraintName)->references($parentColumn)->on($parentTable);

            if ($deleteRule === 'CASCADE') {
                $foreign->cascadeOnDelete();
            } elseif ($deleteRule === 'SET NULL') {
                $foreign->nullOnDelete();
            } else {
                $foreign->restrictOnDelete();
            }
        });
    }

    private function dropForeignKeyIfExists(string $table, string $constraintName): void
    {
        if (! $this->foreignKeyConstraintExists($table, $constraintName)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraintName}`");
    }

    private function getInboundForeignKeyRelations(string $parentTable, string $parentColumn): array
    {
        $schemaName = DB::getDatabaseName();

        return DB::select(
            'SELECT k.TABLE_NAME AS table_name, k.COLUMN_NAME AS column_name, k.CONSTRAINT_NAME AS constraint_name, k.REFERENCED_TABLE_NAME AS referenced_table_name, k.REFERENCED_COLUMN_NAME AS referenced_column_name, rc.DELETE_RULE AS delete_rule FROM information_schema.KEY_COLUMN_USAGE k INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA AND rc.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME = ? AND k.REFERENCED_COLUMN_NAME = ? ORDER BY k.TABLE_NAME, k.COLUMN_NAME',
            [$schemaName, $parentTable, $parentColumn]
        );
    }

    private function foreignKeyReferences(string $childTable, string $childColumn, string $parentTable, string $parentColumn): bool
    {
        $schemaName = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ?',
            [$schemaName, $childTable, $childColumn, $parentTable, $parentColumn]
        );

        return ((int) ($result->total ?? 0)) > 0;
    }

    private function foreignKeyConstraintExists(string $table, string $constraintName): bool
    {
        $schemaName = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = "FOREIGN KEY"',
            [$schemaName, $table, $constraintName]
        );

        return ((int) ($result->total ?? 0)) > 0;
    }

    private function backfillMissingUuids(string $table): void
    {
        DB::table($table)
            ->whereNull('uuid')
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            }, 'id');
    }

    private function assertNoNullUuids(string $table): void
    {
        $nullCount = DB::table($table)->whereNull('uuid')->count();

        if ($nullCount > 0) {
            throw new RuntimeException("Cannot switch {$table} primary key to uuid: {$nullCount} row(s) still have NULL uuid");
        }
    }

    private function assertNoDuplicateUuids(string $table): void
    {
        $duplicateCount = DB::table($table)
            ->select('uuid')
            ->groupBy('uuid')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicateCount > 0) {
            throw new RuntimeException("Cannot switch {$table} primary key to uuid: duplicate uuid detected");
        }
    }

    private function ensureIndex(string $table, string $column, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)");
    }

    private function ensureUniqueIndex(string $table, string $column, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$indexName}` (`{$column}`)");
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $schemaName = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$schemaName, $table, $indexName]
        );

        return ((int) ($result->total ?? 0)) > 0;
    }

    private function getPrimaryKeyColumn(string $table): ?string
    {
        $schemaName = DB::getDatabaseName();

        $row = DB::selectOne(
            'SELECT COLUMN_NAME AS column_name FROM information_schema.key_column_usage WHERE table_schema = ? AND table_name = ? AND constraint_name = ? ORDER BY ordinal_position ASC LIMIT 1',
            [$schemaName, $table, 'PRIMARY']
        );

        return $row ? (string) $row->column_name : null;
    }

    private function hasInboundForeignKeyReferences(string $table, string $column): bool
    {
        $schemaName = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.key_column_usage WHERE referenced_table_schema = ? AND referenced_table_name = ? AND referenced_column_name = ?',
            [$schemaName, $table, $column]
        );

        return ((int) ($result->total ?? 0)) > 0;
    }

    private function uuidColumnName(string $column): string
    {
        if (str_ends_with($column, '_id')) {
            return substr($column, 0, -3).'_uuid';
        }

        return $column.'_uuid';
    }

    private function resolveUuidColumnName(string $childTable, string $childColumn, string $parentTable): string
    {
        $candidate = $this->uuidColumnName($childColumn);

        if (
            $parentTable === 'users'
            && $childColumn === 'employee_id'
            && Schema::hasColumn($childTable, 'employee_uuid')
            && $this->foreignKeyReferences($childTable, 'employee_uuid', 'employee_profiles', 'uuid')
        ) {
            return 'user_uuid';
        }

        return $candidate;
    }

    private function uuidUniqueIndexName(string $table): string
    {
        return $table.'_uuid_unique';
    }

    private function legacyIdIndexName(string $table): string
    {
        return $table.'_legacy_id_idx';
    }

    private function fkName(string $table, string $column): string
    {
        $base = $table.'_'.$column.'_fk';
        if (strlen($base) <= 64) {
            return $base;
        }

        return substr($table, 0, 24).'_'.substr($column, 0, 24).'_'.substr(md5($base), 0, 10);
    }
};
