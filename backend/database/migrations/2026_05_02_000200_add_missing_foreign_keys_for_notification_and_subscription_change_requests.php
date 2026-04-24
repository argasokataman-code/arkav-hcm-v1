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

        // hcm_subscription_change_requests
        $this->nullifyOrphansWhenNullable('hcm_subscription_change_requests', 'current_subscription_uuid', 'subscriptions', 'uuid');
        $this->nullifyOrphansWhenNullable('hcm_subscription_change_requests', 'from_package_uuid', 'packages', 'uuid');
        $this->nullifyOrphansWhenNullable('hcm_subscription_change_requests', 'to_package_uuid', 'packages', 'uuid');
        $this->nullifyOrphansWhenNullable('hcm_subscription_change_requests', 'decided_by_user_uuid', 'users', 'uuid');

        $this->safeForeign('hcm_subscription_change_requests', 'company_uuid', 'companies', 'uuid', 'cascade');
        $this->safeForeign('hcm_subscription_change_requests', 'user_uuid', 'users', 'uuid', 'restrict');
        $this->safeForeign('hcm_subscription_change_requests', 'current_subscription_uuid', 'subscriptions', 'uuid', 'null');
        $this->safeForeign('hcm_subscription_change_requests', 'from_package_uuid', 'packages', 'uuid', 'null');
        $this->safeForeign('hcm_subscription_change_requests', 'to_package_uuid', 'packages', 'uuid', 'null');
        $this->safeForeign('hcm_subscription_change_requests', 'decided_by_user_uuid', 'users', 'uuid', 'null');

        // notification_preferences
        // FK ke users.id tidak bisa dipasang karena users.id bukan unique/primary pada skema ini.
        // Relasi diperbaiki pada migration lanjutan menggunakan user_uuid -> users.uuid.

        // notification_deliveries
        $this->nullifyOrphansWhenNullable('notification_deliveries', 'company_uuid', 'companies', 'uuid');
        $this->safeForeign('notification_deliveries', 'company_uuid', 'companies', 'uuid', 'null');
    }

    public function down(): void
    {
        // Forward-only migration for relation hardening.
    }

    private function safeForeign(string $table, string $column, string $parentTable, string $parentColumn, string $onDelete): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($parentTable, $parentColumn)) {
            return;
        }

        if (! $this->hasUniqueOrPrimaryIndex($parentTable, $parentColumn)) {
            return;
        }

        if (! $this->canApplyForeignKey($table, $column, $parentTable, $parentColumn)) {
            return;
        }

        $name = $this->fkName($table, $column);

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

    private function canApplyForeignKey(string $table, string $column, string $parentTable, string $parentColumn): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return true;
        }

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
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($parentTable, $parentColumn)) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
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
        if (DB::getDriverName() !== 'mysql') {
            return true;
        }

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
        $base = $table . '_' . $column . '_fk';
        if (strlen($base) <= 64) {
            return $base;
        }

        return substr($table, 0, 24) . '_' . substr($column, 0, 24) . '_' . substr(md5($base), 0, 10);
    }
};
