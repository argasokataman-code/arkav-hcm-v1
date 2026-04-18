<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Core tables selected for PK cutover.
     *
     * Notes:
     * - We keep legacy integer id indexed for backward compatibility.
     * - Existing legacy FKs to id can continue to work during transition.
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

        foreach ($this->coreTables as $table) {
            $this->switchPrimaryKeyToUuid($table);
        }
    }

    public function down(): void
    {
        if (! $this->supportsPrimaryKeyCutover()) {
            return;
        }

        foreach ($this->coreTables as $table) {
            $this->switchPrimaryKeyBackToId($table);
        }
    }

    private function supportsPrimaryKeyCutover(): bool
    {
        $driver = DB::getDriverName();

        return in_array($driver, ['mysql', 'mariadb'], true);
    }

    private function switchPrimaryKeyToUuid(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id') || ! Schema::hasColumn($table, 'uuid')) {
            return;
        }

        if ($this->getPrimaryKeyColumn($table) === 'uuid') {
            return;
        }

        $this->backfillMissingUuids($table);
        $this->assertNoNullUuids($table);
        $this->assertNoDuplicateUuids($table);

        // Ensure lookup/index for legacy integer id stays available after PK swap.
        $this->ensureIndex($table, 'id', $this->legacyIdIndexName($table));

        // UUID must be uniquely indexed before becoming PK.
        $this->ensureUniqueIndex($table, 'uuid', $this->uuidUniqueIndexName($table));

        DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY, ADD PRIMARY KEY (`uuid`)");
    }

    private function switchPrimaryKeyBackToId(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id') || ! Schema::hasColumn($table, 'uuid')) {
            return;
        }

        if ($this->getPrimaryKeyColumn($table) === 'id') {
            return;
        }

        // Ensure id has key support before becoming PK again.
        $this->ensureIndex($table, 'id', $this->legacyIdIndexName($table));

        DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY, ADD PRIMARY KEY (`id`)");
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
            'SELECT column_name FROM information_schema.key_column_usage WHERE table_schema = ? AND table_name = ? AND constraint_name = ? ORDER BY ordinal_position ASC LIMIT 1',
            [$schemaName, $table, 'PRIMARY']
        );

        return $row ? (string) $row->column_name : null;
    }

    private function uuidUniqueIndexName(string $table): string
    {
        return $table.'_uuid_unique';
    }

    private function legacyIdIndexName(string $table): string
    {
        return $table.'_legacy_id_idx';
    }
};
