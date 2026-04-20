<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $tables = [
        'teams',
        'invoices',
        'payments',
        'transactions',
        'subscriptions',
        'hcm_payroll_runs',
        'hcm_payroll_periods',
        'hcm_payroll_lines',
        'leave_requests',
        'overtime_requests',
        'tickets',
        'domains',
        'custom_domains',
        'password_reset_tokens',
        'sessions',
    ];

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->ensureUuidColumns();

        foreach ($this->tables as $table) {
            $this->backfillMissingUuids($table);
            $this->finalizePrimaryKeyCutover($table);
        }
    }

    public function down(): void
    {
        // Forward-only migration.
    }

    private function ensureUuidColumns(): void
    {
        foreach (['custom_domains', 'overtime_requests', 'password_reset_tokens', 'sessions'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                if ($table === 'sessions') {
                    $blueprint->uuid('uuid')->nullable()->after('id');
                } else {
                    $blueprint->uuid('uuid')->nullable();
                }
            });
        }
    }

    private function backfillMissingUuids(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'uuid')) {
            return;
        }

        if ($this->primaryKeyColumn($table) === 'id') {
            DB::table($table)
                ->whereNull('uuid')
                ->orderBy('id')
                ->select('id')
                ->chunkById(500, function ($rows) use ($table): void {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
                    }
                }, 'id');

            return;
        }

        $pk = $this->primaryKeyColumn($table);

        if (! $pk) {
            DB::table($table)->whereNull('uuid')->update(['uuid' => (string) Str::uuid()]);
            return;
        }

        $rows = DB::table($table)->whereNull('uuid')->select($pk)->get();
        foreach ($rows as $row) {
            DB::table($table)->where($pk, $row->{$pk})->update(['uuid' => (string) Str::uuid()]);
        }
    }

    private function finalizePrimaryKeyCutover(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'uuid')) {
            return;
        }

        if ($this->primaryKeyColumn($table) === 'uuid') {
            return;
        }

        $this->assertNoNullUuids($table);
        $this->assertNoDuplicateUuids($table);

        if (Schema::hasColumn($table, 'id')) {
            $this->ensureUniqueIndex($table, 'id', $table.'_legacy_id_unique');
        }

        $this->ensureUniqueIndex($table, 'uuid', $table.'_uuid_unique');

        if ($this->primaryKeyColumn($table)) {
            DB::statement("ALTER TABLE `{$table}` DROP PRIMARY KEY, ADD PRIMARY KEY (`uuid`)");
            return;
        }

        DB::statement("ALTER TABLE `{$table}` ADD PRIMARY KEY (`uuid`)");
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

    private function ensureUniqueIndex(string $table, string $column, string $name): void
    {
        if ($this->indexExists($table, $name)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` ADD UNIQUE INDEX `{$name}` (`{$column}`)");
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

    private function primaryKeyColumn(string $table): ?string
    {
        $schemaName = DB::getDatabaseName();

        $row = DB::selectOne(
            'SELECT COLUMN_NAME AS column_name FROM information_schema.key_column_usage WHERE table_schema = ? AND table_name = ? AND constraint_name = ? ORDER BY ordinal_position ASC LIMIT 1',
            [$schemaName, $table, 'PRIMARY']
        );

        return $row ? (string) $row->column_name : null;
    }
};
