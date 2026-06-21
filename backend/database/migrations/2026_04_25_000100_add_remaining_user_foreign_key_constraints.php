<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            // Report Snapshots -> Users (for generated_by_user_id)
            if (Schema::hasColumn('report_snapshots', 'generated_by_user_id')) {
                try {
                    Schema::table('report_snapshots', function (Blueprint $table) {
                        $table->foreign('generated_by_user_id')
                            ->references('id')
                            ->on('users')
                            ->nullOnDelete();
                    });
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate') === false) {
                        throw $e;
                    }
                }
            }

            // Performed by & uploaded by relationships in asset tables
            if (Schema::hasColumn('asset_logs', 'performed_by')) {
                try {
                    Schema::table('asset_logs', function (Blueprint $table) {
                        $table->foreign('performed_by')
                            ->references('id')
                            ->on('users')
                            ->nullOnDelete();
                    });
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate') === false) {
                        throw $e;
                    }
                }
            }

            if (Schema::hasColumn('asset_attachments', 'uploaded_by')) {
                try {
                    Schema::table('asset_attachments', function (Blueprint $table) {
                        $table->foreign('uploaded_by')
                            ->references('id')
                            ->on('users')
                            ->nullOnDelete();
                    });
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate') === false) {
                        throw $e;
                    }
                }
            }

        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $constraints = [
            ['report_snapshots', 'report_snapshots_generated_by_user_id_foreign'],
            ['asset_logs', 'asset_logs_performed_by_foreign'],
            ['asset_attachments', 'asset_attachments_uploaded_by_foreign'],
        ];

        foreach ($constraints as [$table, $constraintName]) {
            if (Schema::hasTable($table) && $this->constraintExists($table, $constraintName)) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraintName}`");
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Helper function to check if a constraint exists
     */
    private function constraintExists(string $tableName, string $constraintName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        $constraints = \DB::select('
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
        ', [env('DB_DATABASE'), $tableName, $constraintName]);

        return count($constraints) > 0;
    }
};
