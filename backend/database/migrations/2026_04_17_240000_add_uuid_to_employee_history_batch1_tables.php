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
            'employee_employment_history',
            'employee_assignments',
            'employee_compensations',
            'employee_contracts',
            'employee_bank_accounts',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if (! Schema::hasColumn($tableName, 'employee_uuid')) {
                    $table->uuid('employee_uuid')->nullable()->after('employee_id');
                }
            });
        }

        $this->backfillRowUuids($tables);
        $this->backfillEmployeeUuids($tables);
        $this->addIndexes($tables);
        $this->addForeignKeys($tables);
    }

    public function down(): void
    {
        // Intentionally minimal: UUID migration is forward-only for this batch.
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
                        DB::table($tableName)
                            ->where('id', $row->id)
                            ->update(['uuid' => (string) Str::uuid()]);
                    }
                }, 'id');
        }
    }

    private function backfillEmployeeUuids(array $tables): void
    {
        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'employee_uuid')) {
                continue;
            }

            if (DB::getDriverName() === 'mysql') {
                DB::statement("UPDATE {$tableName} t JOIN employee_profiles p ON t.employee_id = p.id SET t.employee_uuid = p.uuid WHERE t.employee_id IS NOT NULL AND t.employee_uuid IS NULL");
                continue;
            }

            $rows = DB::table($tableName)
                ->whereNotNull('employee_id')
                ->whereNull('employee_uuid')
                ->select('id', 'employee_id')
                ->get();

            foreach ($rows as $row) {
                $employeeUuid = DB::table('employee_profiles')->where('id', $row->employee_id)->value('uuid');
                DB::table($tableName)->where('id', $row->id)->update(['employee_uuid' => $employeeUuid]);
            }
        }
    }

    private function addIndexes(array $tables): void
    {
        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $this->safeIndex($tableName, 'uuid', $tableName . '_uuid_idx');
            $this->safeIndex($tableName, 'employee_uuid', $tableName . '_employee_uuid_idx');
        }
    }

    private function addForeignKeys(array $tables): void
    {
        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'employee_uuid')) {
                continue;
            }

            $this->safeForeign($tableName, 'employee_uuid', 'employee_profiles', 'uuid', $tableName . '_employee_uuid_fk', 'cascade');
        }
    }

    private function safeIndex(string $table, string $column, string $name): void
    {
        try {
            Schema::table($table, function (Blueprint $tableBlueprint) use ($column, $name): void {
                $tableBlueprint->index($column, $name);
            });
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }

    private function safeForeign(string $table, string $column, string $parentTable, string $parentColumn, string $name, string $onDelete): void
    {
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
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }
};
