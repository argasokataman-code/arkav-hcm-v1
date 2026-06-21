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
            'performance_cycles',
            'performance_indicator_templates',
            'performance_indicator_items',
            'performance_reviews',
            'performance_review_scores',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'uuid')) {
                    $table->uuid('uuid')->nullable()->unique()->after('id');
                }

                if ($tableName === 'performance_indicator_items' && ! Schema::hasColumn($tableName, 'template_uuid')) {
                    $table->uuid('template_uuid')->nullable()->after('template_id');
                }

                if ($tableName === 'performance_reviews') {
                    if (! Schema::hasColumn($tableName, 'company_uuid')) {
                        $table->uuid('company_uuid')->nullable()->after('company_id');
                    }
                    if (! Schema::hasColumn($tableName, 'cycle_uuid')) {
                        $table->uuid('cycle_uuid')->nullable()->after('cycle_id');
                    }
                    if (! Schema::hasColumn($tableName, 'user_uuid')) {
                        $table->uuid('user_uuid')->nullable()->after('user_id');
                    }
                    if (! Schema::hasColumn($tableName, 'manager_user_uuid')) {
                        $table->uuid('manager_user_uuid')->nullable()->after('manager_user_id');
                    }
                    if (! Schema::hasColumn($tableName, 'template_uuid')) {
                        $table->uuid('template_uuid')->nullable()->after('template_id');
                    }
                }

                if ($tableName === 'performance_review_scores') {
                    if (! Schema::hasColumn($tableName, 'review_uuid')) {
                        $table->uuid('review_uuid')->nullable()->after('review_id');
                    }
                    if (! Schema::hasColumn($tableName, 'item_uuid')) {
                        $table->uuid('item_uuid')->nullable()->after('item_id');
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
        $this->syncUuidByJoin('performance_indicator_items', 'template_id', 'template_uuid', 'performance_indicator_templates');

        $this->syncUuidByJoin('performance_reviews', 'company_id', 'company_uuid', 'companies');
        $this->syncUuidByJoin('performance_reviews', 'cycle_id', 'cycle_uuid', 'performance_cycles');
        $this->syncUuidByJoin('performance_reviews', 'user_id', 'user_uuid', 'users');
        $this->syncUuidByJoin('performance_reviews', 'manager_user_id', 'manager_user_uuid', 'users');
        $this->syncUuidByJoin('performance_reviews', 'template_id', 'template_uuid', 'performance_indicator_templates');

        $this->syncUuidByJoin('performance_review_scores', 'review_id', 'review_uuid', 'performance_reviews');
        $this->syncUuidByJoin('performance_review_scores', 'item_id', 'item_uuid', 'performance_indicator_items');
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
            ['performance_indicator_items', 'template_uuid', 'performance_indicator_items_template_uuid_idx'],
            ['performance_reviews', 'company_uuid', 'performance_reviews_company_uuid_idx'],
            ['performance_reviews', 'cycle_uuid', 'performance_reviews_cycle_uuid_idx'],
            ['performance_reviews', 'user_uuid', 'performance_reviews_user_uuid_idx'],
            ['performance_reviews', 'manager_user_uuid', 'performance_reviews_manager_user_uuid_idx'],
            ['performance_reviews', 'template_uuid', 'performance_reviews_template_uuid_idx'],
            ['performance_review_scores', 'review_uuid', 'performance_review_scores_review_uuid_idx'],
            ['performance_review_scores', 'item_uuid', 'performance_review_scores_item_uuid_idx'],
        ];

        foreach ($indexes as [$table, $column, $name]) {
            $this->safeIndex($table, $column, $name);
        }
    }

    private function addForeignKeys(): void
    {
        $this->safeForeign('performance_indicator_items', 'template_uuid', 'performance_indicator_templates', 'uuid', 'performance_indicator_items_template_uuid_fk', 'cascade');

        $this->safeForeign('performance_reviews', 'company_uuid', 'companies', 'uuid', 'performance_reviews_company_uuid_fk', 'null');
        $this->safeForeign('performance_reviews', 'cycle_uuid', 'performance_cycles', 'uuid', 'performance_reviews_cycle_uuid_fk', 'cascade');
        $this->safeForeign('performance_reviews', 'user_uuid', 'users', 'uuid', 'performance_reviews_user_uuid_fk', 'cascade');
        $this->safeForeign('performance_reviews', 'manager_user_uuid', 'users', 'uuid', 'performance_reviews_manager_user_uuid_fk', 'null');
        $this->safeForeign('performance_reviews', 'template_uuid', 'performance_indicator_templates', 'uuid', 'performance_reviews_template_uuid_fk', 'restrict');

        $this->safeForeign('performance_review_scores', 'review_uuid', 'performance_reviews', 'uuid', 'performance_review_scores_review_uuid_fk', 'cascade');
        $this->safeForeign('performance_review_scores', 'item_uuid', 'performance_indicator_items', 'uuid', 'performance_review_scores_item_uuid_fk', 'cascade');
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
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'duplicate') === false && stripos($e->getMessage(), 'exists') === false) {
                throw $e;
            }
        }
    }
};
