<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasTable('payroll_settings_snapshots')) {
            return;
        }

        // Skip if migration 2026_05_10_000000 already migrated to UUID
        if (Schema::hasColumn('payroll_settings_snapshots', 'company_uuid')) {
            return;
        }

        // Keep only snapshots that still map to an existing tenant.
        DB::statement(
            'DELETE s FROM payroll_settings_snapshots s '
            .'LEFT JOIN companies c ON c.id = s.company_id '
            .'WHERE c.id IS NULL'
        );

        // Preserve snapshot record when actor user is deleted.
        DB::statement(
            'UPDATE payroll_settings_snapshots s '
            .'LEFT JOIN users u ON u.id = s.user_id '
            .'SET s.user_id = NULL '
            .'WHERE s.user_id IS NOT NULL AND u.id IS NULL'
        );

        $this->safeIndex('payroll_settings_snapshots', 'company_id', 'payroll_settings_snapshots_company_id_idx');
        $this->safeIndex('payroll_settings_snapshots', 'user_id', 'payroll_settings_snapshots_user_id_idx');

        $this->safeForeign(
            'payroll_settings_snapshots',
            'company_id',
            'companies',
            'id',
            'payroll_settings_snapshots_company_id_fk',
            'cascade'
        );

        $this->safeForeign(
            'payroll_settings_snapshots',
            'user_id',
            'users',
            'id',
            'payroll_settings_snapshots_user_id_fk',
            'null'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('payroll_settings_snapshots')) {
            return;
        }

        if ($this->hasConstraint('payroll_settings_snapshots', 'payroll_settings_snapshots_company_id_fk')) {
            Schema::table('payroll_settings_snapshots', function (Blueprint $table): void {
                $table->dropForeign('payroll_settings_snapshots_company_id_fk');
            });
        }

        if ($this->hasConstraint('payroll_settings_snapshots', 'payroll_settings_snapshots_user_id_fk')) {
            Schema::table('payroll_settings_snapshots', function (Blueprint $table): void {
                $table->dropForeign('payroll_settings_snapshots_user_id_fk');
            });
        }
    }

    private function safeForeign(
        string $table,
        string $column,
        string $parentTable,
        string $parentColumn,
        string $constraintName,
        string $onDelete
    ): void {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $column)
            || ! Schema::hasTable($parentTable)
            || ! Schema::hasColumn($parentTable, $parentColumn)) {
            return;
        }

        if (! $this->hasUniqueOrPrimaryIndex($parentTable, $parentColumn)) {
            return;
        }

        if (! $this->canApplyForeignKey($table, $column, $parentTable, $parentColumn)) {
            return;
        }

        if ($this->hasConstraint($table, $constraintName)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $parentTable, $parentColumn, $constraintName, $onDelete): void {
                $foreign = $blueprint->foreign($column, $constraintName)
                    ->references($parentColumn)
                    ->on($parentTable);

                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } elseif ($onDelete === 'null') {
                    $foreign->nullOnDelete();
                } else {
                    $foreign->restrictOnDelete();
                }
            });
        } catch (Throwable $e) {
            $msg = strtolower($e->getMessage());
            if (
                str_contains($msg, 'duplicate')
                || str_contains($msg, 'exists')
                || str_contains($msg, 'already')
                || str_contains($msg, 'cannot add foreign key constraint')
                || str_contains($msg, 'foreign key constraint is incorrectly formed')
                || str_contains($msg, 'missing unique key')
            ) {
                return;
            }

            throw $e;
        }
    }

    private function safeIndex(string $table, string $column, string $indexName): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        if ($this->hasIndex($table, $indexName)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $indexName): void {
                $blueprint->index($column, $indexName);
            });
        } catch (Throwable $e) {
            $msg = strtolower($e->getMessage());
            if (str_contains($msg, 'duplicate') || str_contains($msg, 'exists') || str_contains($msg, 'already')) {
                return;
            }

            throw $e;
        }
    }

    private function canApplyForeignKey(string $table, string $column, string $parentTable, string $parentColumn): bool
    {
        $query = "SELECT COUNT(*) AS total\n"
            ."FROM {$table} t\n"
            ."LEFT JOIN {$parentTable} p ON t.{$column} = p.{$parentColumn}\n"
            ."WHERE t.{$column} IS NOT NULL AND p.{$parentColumn} IS NULL";

        $result = DB::selectOne($query);

        return (int) ($result->total ?? 0) === 0;
    }

    private function hasUniqueOrPrimaryIndex(string $table, string $column): bool
    {
        $rows = DB::table('information_schema.STATISTICS')
            ->select('INDEX_NAME', 'NON_UNIQUE')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->get();

        foreach ($rows as $row) {
            $indexName = strtoupper((string) ($row->INDEX_NAME ?? ''));
            $nonUnique = (int) ($row->NON_UNIQUE ?? 1);
            if ($indexName === 'PRIMARY' || $nonUnique === 0) {
                return true;
            }
        }

        return false;
    }

    private function hasConstraint(string $table, string $constraintName): bool
    {
        $row = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->select('CONSTRAINT_NAME')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->first();

        return $row !== null;
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $row = DB::table('information_schema.STATISTICS')
            ->select('INDEX_NAME')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->first();

        return $row !== null;
    }
};
