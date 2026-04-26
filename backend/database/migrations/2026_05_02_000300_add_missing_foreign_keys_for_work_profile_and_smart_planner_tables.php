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

        $relations = [
            ['hcm_payroll_work_profiles', 'company_id', 'companies', 'id', 'null'],

            ['hcm_employee_work_arrangements', 'company_id', 'companies', 'id', 'null'],
            ['hcm_employee_work_arrangements', 'user_id', 'users', 'id', 'cascade'],
            ['hcm_employee_work_arrangements', 'hcm_payroll_work_profile_id', 'hcm_payroll_work_profiles', 'id', 'null'],

            ['hcm_smart_planner_settings', 'company_id', 'companies', 'id', 'null'],
            ['hcm_smart_planner_settings', 'created_by_user_id', 'users', 'id', 'null'],
            ['hcm_smart_planner_settings', 'updated_by_user_id', 'users', 'id', 'null'],

            ['hcm_schedule_rosters', 'company_id', 'companies', 'id', 'null'],
            ['hcm_schedule_rosters', 'user_id', 'users', 'id', 'cascade'],
            ['hcm_schedule_rosters', 'hcm_shift_id', 'hcm_shifts', 'id', 'null'],
            ['hcm_schedule_rosters', 'published_by_user_id', 'users', 'id', 'null'],
        ];

        foreach ($relations as [$table, $column, $parentTable, $parentColumn, $onDelete]) {
            $this->nullifyOrphansWhenNullable($table, $column, $parentTable, $parentColumn);
            $this->safeIndex($table, $column, $this->indexName($table, $column));
            $this->safeForeign($table, $column, $parentTable, $parentColumn, $this->fkName($table, $column), $onDelete);
        }
    }

    public function down(): void
    {
        // Forward-only migration for relation hardening.
    }

    private function safeForeign(string $table, string $column, string $parentTable, string $parentColumn, string $name, string $onDelete): void
    {
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

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $parentTable, $parentColumn, $name, $onDelete): void {
                $foreign = $blueprint->foreign($column, $name)->references($parentColumn)->on($parentTable);

                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } elseif ($onDelete === 'null') {
                    $foreign->nullOnDelete();
                } else {
                    $foreign->restrictOnDelete();
                }
            });
        } catch (\Throwable $e) {
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

    private function safeIndex(string $table, string $column, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $name): void {
                $blueprint->index($column, $name);
            });
        } catch (\Throwable $e) {
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
            . "FROM {$table} t\n"
            . "LEFT JOIN {$parentTable} p ON t.{$column} = p.{$parentColumn}\n"
            . "WHERE t.{$column} IS NOT NULL AND p.{$parentColumn} IS NULL";

        $result = DB::selectOne($query);
        $count = (int) ($result->total ?? 0);

        return $count === 0;
    }

    private function nullifyOrphansWhenNullable(string $table, string $column, string $parentTable, string $parentColumn): void
    {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $column)
            || ! Schema::hasTable($parentTable)
            || ! Schema::hasColumn($parentTable, $parentColumn)) {
            return;
        }

        if (! $this->isNullableColumn($table, $column)) {
            return;
        }

        DB::statement("UPDATE {$table} t LEFT JOIN {$parentTable} p ON t.{$column} = p.{$parentColumn} SET t.{$column} = NULL WHERE t.{$column} IS NOT NULL AND p.{$parentColumn} IS NULL");
    }

    private function isNullableColumn(string $table, string $column): bool
    {
        $row = DB::table('information_schema.COLUMNS')
            ->select('IS_NULLABLE')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->first();

        return strtoupper((string) ($row->IS_NULLABLE ?? 'NO')) === 'YES';
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

    private function fkName(string $table, string $column): string
    {
        $base = $table.'_'.$column.'_fk';
        if (strlen($base) <= 64) {
            return $base;
        }

        return substr($table, 0, 24).'_'.substr($column, 0, 24).'_'.substr(md5($base), 0, 10);
    }

    private function indexName(string $table, string $column): string
    {
        $base = $table.'_'.$column.'_idx';
        if (strlen($base) <= 64) {
            return $base;
        }

        return substr($table, 0, 24).'_'.substr($column, 0, 24).'_'.substr(md5($base), 0, 10);
    }
};
