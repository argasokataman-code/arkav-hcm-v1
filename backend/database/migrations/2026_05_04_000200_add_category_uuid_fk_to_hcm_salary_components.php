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
        if (! $this->isMySqlFamily()) {
            return;
        }

        if (! Schema::hasTable('hcm_salary_components') || ! Schema::hasTable('hcm_salary_component_categories')) {
            return;
        }

        $this->ensureCategoryUuids();
        $this->ensureCategoryUuidColumn();
        $this->backfillCategoryUuid();
        $this->nullifyOrphanCategoryUuids();
        $this->safeIndex('hcm_salary_components', 'category_uuid', 'hcm_salary_components_category_uuid_idx');
        $this->safeForeign(
            'hcm_salary_components',
            'category_uuid',
            'hcm_salary_component_categories',
            'uuid',
            'hcm_salary_components_category_uuid_fk',
            'null'
        );
    }

    public function down(): void
    {
        // Forward-only migration.
    }

    private function isMySqlFamily(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function ensureCategoryUuids(): void
    {
        if (! Schema::hasColumn('hcm_salary_component_categories', 'uuid')) {
            return;
        }

        DB::table('hcm_salary_component_categories')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('hcm_salary_component_categories')
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            }, 'id');
    }

    private function ensureCategoryUuidColumn(): void
    {
        if (Schema::hasColumn('hcm_salary_components', 'category_uuid')) {
            return;
        }

        Schema::table('hcm_salary_components', function (Blueprint $table): void {
            $table->uuid('category_uuid')->nullable()->after('category');
        });
    }

    private function backfillCategoryUuid(): void
    {
        if (! Schema::hasColumn('hcm_salary_components', 'category_uuid')) {
            return;
        }

        DB::statement(
            'UPDATE hcm_salary_components c '
            . 'JOIN hcm_salary_component_categories cat '
            . 'ON cat.kind = c.kind AND cat.code = c.category '
            . 'SET c.category_uuid = cat.uuid '
            . 'WHERE c.category_uuid IS NULL'
        );
    }

    private function nullifyOrphanCategoryUuids(): void
    {
        if (! Schema::hasColumn('hcm_salary_components', 'category_uuid')) {
            return;
        }

        DB::statement(
            'UPDATE hcm_salary_components c '
            . 'LEFT JOIN hcm_salary_component_categories cat ON c.category_uuid = cat.uuid '
            . 'SET c.category_uuid = NULL '
            . 'WHERE c.category_uuid IS NOT NULL AND cat.uuid IS NULL'
        );
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
            $message = strtolower($e->getMessage());
            if (! str_contains($message, 'duplicate') && ! str_contains($message, 'exists')) {
                throw $e;
            }
        }
    }

    private function safeForeign(
        string $table,
        string $column,
        string $parentTable,
        string $parentColumn,
        string $name,
        string $onDelete
    ): void {
        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $column)
            || ! Schema::hasTable($parentTable)
            || ! Schema::hasColumn($parentTable, $parentColumn)) {
            return;
        }

        if ($this->foreignExists($table, $name)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $parentTable, $parentColumn, $name, $onDelete): void {
                $foreign = $blueprint->foreign($column, $name)->references($parentColumn)->on($parentTable);

                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } elseif ($onDelete === 'restrict') {
                    $foreign->restrictOnDelete();
                } else {
                    $foreign->nullOnDelete();
                }
            });
        } catch (\Throwable $e) {
            $message = strtolower($e->getMessage());
            if (! str_contains($message, 'duplicate') && ! str_contains($message, 'exists')) {
                throw $e;
            }
        }
    }

    private function foreignExists(string $table, string $constraintName): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
