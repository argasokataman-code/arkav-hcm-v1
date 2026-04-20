<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'asset_categories',
        'assets',
        'asset_assignments',
        'asset_logs',
        'asset_attachments',
        'auth_tokens',
        'attendance_records',
        'audit_logs',
    ];

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach ($this->tables as $table) {
            $this->finalizePrimaryKeyCutover($table);
        }
    }

    public function down(): void
    {
        // Forward-only migration.
    }

    private function finalizePrimaryKeyCutover(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id') || ! Schema::hasColumn($table, 'uuid')) {
            return;
        }

        if ($this->getPrimaryKeyColumn($table) === 'uuid') {
            return;
        }

        $this->assertNoNullUuids($table);
        $this->assertNoDuplicateUuids($table);

        // Preserve integer compatibility for legacy code and existing id-based relations.
        $this->ensureUniqueIndex($table, 'id', $this->legacyIdUniqueIndexName($table));
        $this->ensureUniqueIndex($table, 'uuid', $this->uuidUniqueIndexName($table));

        DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY, ADD PRIMARY KEY (`uuid`)");
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

    private function legacyIdUniqueIndexName(string $table): string
    {
        return $table.'_legacy_id_unique';
    }

    private function uuidUniqueIndexName(string $table): string
    {
        return $table.'_uuid_unique';
    }
};
