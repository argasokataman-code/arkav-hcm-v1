<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->dropForeignIfExists('assets', 'assets_asset_category_id_fk');
        $this->dropForeignIfExists('asset_assignments', 'asset_assignments_asset_id_fk');
        $this->dropForeignIfExists('asset_attachments', 'asset_attachments_asset_id_fk');
        $this->dropForeignIfExists('asset_logs', 'asset_logs_asset_id_fk');
    }

    public function down(): void
    {
        // Forward-only migration.
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        $schemaName = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(*) AS total FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = "FOREIGN KEY"',
            [$schemaName, $table, $constraint]
        );

        if (((int) ($result->total ?? 0)) === 0) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
    }
};
