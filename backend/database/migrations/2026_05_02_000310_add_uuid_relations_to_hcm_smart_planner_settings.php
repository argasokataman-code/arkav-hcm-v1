<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_smart_planner_settings')) {
            return;
        }

        Schema::table('hcm_smart_planner_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('hcm_smart_planner_settings', 'company_uuid')) {
                $table->uuid('company_uuid')->nullable()->after('company_id');
            }
            if (! Schema::hasColumn('hcm_smart_planner_settings', 'created_by_user_uuid')) {
                $table->uuid('created_by_user_uuid')->nullable()->after('created_by_user_id');
            }
            if (! Schema::hasColumn('hcm_smart_planner_settings', 'updated_by_user_uuid')) {
                $table->uuid('updated_by_user_uuid')->nullable()->after('updated_by_user_id');
            }
        });

        $this->backfillUuidColumns();

        $this->safeIndex('hcm_smart_planner_settings', 'company_uuid', 'hcm_smart_planner_settings_company_uuid_idx');
        $this->safeIndex('hcm_smart_planner_settings', 'created_by_user_uuid', 'hcm_smart_planner_settings_created_by_user_uuid_idx');
        $this->safeIndex('hcm_smart_planner_settings', 'updated_by_user_uuid', 'hcm_smart_planner_settings_updated_by_user_uuid_idx');

        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->safeForeign('hcm_smart_planner_settings', 'company_uuid', 'companies', 'uuid', 'hcm_smart_planner_settings_company_uuid_fk', 'null');
        $this->safeForeign('hcm_smart_planner_settings', 'created_by_user_uuid', 'users', 'uuid', 'hcm_smart_planner_settings_created_by_user_uuid_fk', 'null');
        $this->safeForeign('hcm_smart_planner_settings', 'updated_by_user_uuid', 'users', 'uuid', 'hcm_smart_planner_settings_updated_by_user_uuid_fk', 'null');
    }

    public function down(): void
    {
        // Forward-only migration for UUID relation hardening.
    }

    private function backfillUuidColumns(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('users')) {
            return;
        }

        DB::table('hcm_smart_planner_settings')
            ->orderBy('id')
            ->select([
                'id',
                'company_id',
                'company_uuid',
                'created_by_user_id',
                'created_by_user_uuid',
                'updated_by_user_id',
                'updated_by_user_uuid',
            ])
            ->chunkById(250, function ($rows): void {
                $companyIds = collect($rows)
                    ->pluck('company_id')
                    ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->values();

                $userIds = collect($rows)
                    ->flatMap(function ($row): array {
                        return [$row->created_by_user_id, $row->updated_by_user_id];
                    })
                    ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->values();

                $companyUuidMap = $companyIds->isEmpty()
                    ? collect()
                    : DB::table('companies')->whereIn('id', $companyIds)->pluck('uuid', 'id');
                $userUuidMap = $userIds->isEmpty()
                    ? collect()
                    : DB::table('users')->whereIn('id', $userIds)->pluck('uuid', 'id');

                foreach ($rows as $row) {
                    $update = [];

                    $companyUuid = isset($companyUuidMap[$row->company_id]) ? (string) $companyUuidMap[$row->company_id] : null;
                    if ((! is_string($row->company_uuid) || trim($row->company_uuid) === '') && is_string($companyUuid) && trim($companyUuid) !== '') {
                        $update['company_uuid'] = $companyUuid;
                    }

                    $createdByUuid = isset($userUuidMap[$row->created_by_user_id]) ? (string) $userUuidMap[$row->created_by_user_id] : null;
                    if ((! is_string($row->created_by_user_uuid) || trim($row->created_by_user_uuid) === '') && is_string($createdByUuid) && trim($createdByUuid) !== '') {
                        $update['created_by_user_uuid'] = $createdByUuid;
                    }

                    $updatedByUuid = isset($userUuidMap[$row->updated_by_user_id]) ? (string) $userUuidMap[$row->updated_by_user_id] : null;
                    if ((! is_string($row->updated_by_user_uuid) || trim($row->updated_by_user_uuid) === '') && is_string($updatedByUuid) && trim($updatedByUuid) !== '') {
                        $update['updated_by_user_uuid'] = $updatedByUuid;
                    }

                    if (! empty($update)) {
                        DB::table('hcm_smart_planner_settings')->where('id', $row->id)->update($update);
                    }
                }
            }, 'id');
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
        } catch (Throwable $e) {
            $message = strtolower($e->getMessage());
            if (str_contains($message, 'duplicate') || str_contains($message, 'exists') || str_contains($message, 'already')) {
                return;
            }

            throw $e;
        }
    }

    private function safeForeign(string $table, string $column, string $parentTable, string $parentColumn, string $name, string $onDelete): void
    {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $column)
            || ! Schema::hasTable($parentTable)
            || ! Schema::hasColumn($parentTable, $parentColumn)) {
            return;
        }

        if ($this->foreignKeyExists($table, $column, $parentTable, $parentColumn)) {
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

                if ($onDelete === 'null') {
                    $foreign->nullOnDelete();
                } elseif ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } else {
                    $foreign->restrictOnDelete();
                }
            });
        } catch (Throwable $e) {
            $message = strtolower($e->getMessage());
            if (
                str_contains($message, 'duplicate')
                || str_contains($message, 'exists')
                || str_contains($message, 'already')
                || str_contains($message, 'cannot add foreign key constraint')
                || str_contains($message, 'foreign key constraint is incorrectly formed')
                || str_contains($message, 'missing unique key')
            ) {
                return;
            }

            throw $e;
        }
    }

    private function canApplyForeignKey(string $table, string $column, string $parentTable, string $parentColumn): bool
    {
        $result = DB::selectOne(
            "SELECT COUNT(*) AS total FROM {$table} child LEFT JOIN {$parentTable} parent ON child.{$column} = parent.{$parentColumn} WHERE child.{$column} IS NOT NULL AND parent.{$parentColumn} IS NULL"
        );

        return ((int) ($result->total ?? 0)) === 0;
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

    private function foreignKeyExists(string $table, string $column, string $parentTable, string $parentColumn): bool
    {
        $result = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->selectRaw('COUNT(*) AS total')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->where('REFERENCED_TABLE_NAME', $parentTable)
            ->where('REFERENCED_COLUMN_NAME', $parentColumn)
            ->first();

        return ((int) ($result->total ?? 0)) > 0;
    }
};
