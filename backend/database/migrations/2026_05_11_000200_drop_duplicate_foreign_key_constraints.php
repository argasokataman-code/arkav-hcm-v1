<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::select(
            'SELECT
                k.table_name,
                k.column_name,
                k.referenced_table_name,
                k.referenced_column_name,
                GROUP_CONCAT(k.constraint_name ORDER BY k.constraint_name SEPARATOR ",") AS constraint_names,
                COUNT(*) AS total_constraints
             FROM information_schema.key_column_usage k
             WHERE k.table_schema = DATABASE()
               AND k.referenced_table_name IS NOT NULL
             GROUP BY
                k.table_name,
                k.column_name,
                k.referenced_table_name,
                k.referenced_column_name
             HAVING COUNT(*) > 1'
        );

        foreach ($duplicates as $row) {
            $tableName = (string) ($row->table_name ?? $row->TABLE_NAME ?? '');
            $constraintNames = (string) ($row->constraint_names ?? $row->CONSTRAINT_NAMES ?? '');
            $names = array_values(array_filter(explode(',', $constraintNames)));

            if ($tableName === '' || count($names) <= 1 || ! Schema::hasTable($tableName)) {
                continue;
            }

            // Keep one canonical FK edge and drop the rest.
            array_shift($names);

            foreach ($names as $constraintName) {
                if (! $this->foreignConstraintExists($constraintName)) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($constraintName): void {
                    $table->dropForeign($constraintName);
                });
            }
        }
    }

    public function down(): void
    {
        // Intentionally left blank: dropped duplicate FKs are redundant edges.
    }

    private function foreignConstraintExists(string $constraintName): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
             FROM information_schema.table_constraints
             WHERE constraint_schema = DATABASE()
               AND constraint_type = ?
               AND constraint_name = ?',
            ['FOREIGN KEY', $constraintName]
        );

        return ((int) ($row->aggregate ?? 0)) > 0;
    }
};
