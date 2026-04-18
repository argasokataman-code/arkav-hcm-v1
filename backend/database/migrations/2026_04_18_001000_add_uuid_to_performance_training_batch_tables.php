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
            'performance_goals',
            'hcm_training_types',
            'hcm_trainers',
            'hcm_trainings',
            'hcm_training_participants',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if ($tableName === 'performance_goals') {
                    if (! Schema::hasColumn($tableName, 'goal_type_uuid')) {
                        $table->uuid('goal_type_uuid')->nullable()->after('goal_type_id');
                    }
                    if (! Schema::hasColumn($tableName, 'user_uuid')) {
                        $table->uuid('user_uuid')->nullable()->after('user_id');
                    }
                    if (! Schema::hasColumn($tableName, 'manager_user_uuid')) {
                        $table->uuid('manager_user_uuid')->nullable()->after('manager_user_id');
                    }
                }

                if ($tableName === 'hcm_trainings') {
                    if (! Schema::hasColumn($tableName, 'training_type_uuid')) {
                        $table->uuid('training_type_uuid')->nullable()->after('training_type_id');
                    }
                    if (Schema::hasColumn($tableName, 'trainer_id') && ! Schema::hasColumn($tableName, 'trainer_uuid')) {
                        $table->uuid('trainer_uuid')->nullable()->after('trainer_id');
                    }
                }

                if ($tableName === 'hcm_training_participants') {
                    if (! Schema::hasColumn($tableName, 'training_uuid')) {
                        $table->uuid('training_uuid')->nullable()->after('training_id');
                    }
                    if (! Schema::hasColumn($tableName, 'user_uuid')) {
                        $table->uuid('user_uuid')->nullable()->after('user_id');
                    }
                }
            });
        }

        $this->backfillRowUuids($tables);
        $this->backfillForeignUuids();
        $this->addIndexes();
        $this->addForeignKeys();
    }

    public function down(): void
    {
        // Forward-only batch.
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
                        DB::table($tableName)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
                    }
                }, 'id');
        }
    }

    private function backfillForeignUuids(): void
    {
        $this->syncUuidByJoin('performance_goals', 'goal_type_id', 'goal_type_uuid', 'performance_goal_types');
        $this->syncUuidByJoin('performance_goals', 'user_id', 'user_uuid', 'users');
        $this->syncUuidByJoin('performance_goals', 'manager_user_id', 'manager_user_uuid', 'users');

        $this->syncUuidByJoin('hcm_trainings', 'training_type_id', 'training_type_uuid', 'hcm_training_types');
        $this->syncUuidByJoin('hcm_trainings', 'trainer_id', 'trainer_uuid', 'hcm_trainers');

        $this->syncUuidByJoin('hcm_training_participants', 'training_id', 'training_uuid', 'hcm_trainings');
        $this->syncUuidByJoin('hcm_training_participants', 'user_id', 'user_uuid', 'users');
    }

    private function syncUuidByJoin(string $table, string $legacyColumn, string $uuidColumn, string $parentTable): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $legacyColumn) || ! Schema::hasColumn($table, $uuidColumn)) {
            return;
        }

        if (! Schema::hasTable($parentTable) || ! Schema::hasColumn($parentTable, 'uuid')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE {$table} t JOIN {$parentTable} p ON t.{$legacyColumn} = p.id SET t.{$uuidColumn} = p.uuid WHERE t.{$legacyColumn} IS NOT NULL AND t.{$uuidColumn} IS NULL");
            return;
        }

        $rows = DB::table($table)->whereNotNull($legacyColumn)->whereNull($uuidColumn)->select('id', $legacyColumn)->get();
        foreach ($rows as $row) {
            $uuid = DB::table($parentTable)->where('id', $row->{$legacyColumn})->value('uuid');
            DB::table($table)->where('id', $row->id)->update([$uuidColumn => $uuid]);
        }
    }

    private function addIndexes(): void
    {
        $indexes = [
            ['performance_goals', 'goal_type_uuid', 'performance_goals_goal_type_uuid_idx'],
            ['performance_goals', 'user_uuid', 'performance_goals_user_uuid_idx'],
            ['performance_goals', 'manager_user_uuid', 'performance_goals_manager_user_uuid_idx'],
            ['hcm_trainings', 'training_type_uuid', 'hcm_trainings_training_type_uuid_idx'],
            ['hcm_trainings', 'trainer_uuid', 'hcm_trainings_trainer_uuid_idx'],
            ['hcm_training_participants', 'training_uuid', 'hcm_training_participants_training_uuid_idx'],
            ['hcm_training_participants', 'user_uuid', 'hcm_training_participants_user_uuid_idx'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            $this->safeIndex($table, $column, $name);
        }
    }

    private function addForeignKeys(): void
    {
        $this->safeForeign('performance_goals', 'goal_type_uuid', 'performance_goal_types', 'uuid', 'performance_goals_goal_type_uuid_fk', 'null');
        $this->safeForeign('performance_goals', 'user_uuid', 'users', 'uuid', 'performance_goals_user_uuid_fk', 'cascade');
        $this->safeForeign('performance_goals', 'manager_user_uuid', 'users', 'uuid', 'performance_goals_manager_user_uuid_fk', 'null');

        $this->safeForeign('hcm_trainings', 'training_type_uuid', 'hcm_training_types', 'uuid', 'hcm_trainings_training_type_uuid_fk', 'null');
        $this->safeForeign('hcm_trainings', 'trainer_uuid', 'hcm_trainers', 'uuid', 'hcm_trainings_trainer_uuid_fk', 'null');

        $this->safeForeign('hcm_training_participants', 'training_uuid', 'hcm_trainings', 'uuid', 'hcm_training_participants_training_uuid_fk', 'cascade');
        $this->safeForeign('hcm_training_participants', 'user_uuid', 'users', 'uuid', 'hcm_training_participants_user_uuid_fk', 'cascade');
    }

    private function safeIndex(string $table, string $column, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

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
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || ! Schema::hasTable($parentTable) || ! Schema::hasColumn($parentTable, $parentColumn)) {
            return;
        }

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
