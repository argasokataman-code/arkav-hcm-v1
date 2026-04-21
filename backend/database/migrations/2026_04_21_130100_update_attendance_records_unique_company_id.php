<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_records')) {
            return;
        }

        if (! Schema::hasColumn('attendance_records', 'company_id')) {
            return;
        }

        if (! $this->indexExists('attendance_records', 'attendance_records_user_id_index')) {
            Schema::table('attendance_records', function (Blueprint $table): void {
                $table->index('user_id', 'attendance_records_user_id_index');
            });
        }

        Schema::table('attendance_records', function (Blueprint $table): void {
            if ($this->indexExists('attendance_records', 'attendance_records_user_id_work_date_unique')) {
                $table->dropUnique('attendance_records_user_id_work_date_unique');
            }

            if (! $this->indexExists('attendance_records', 'attendance_records_company_user_date_unique')) {
                $table->unique(['company_id', 'user_id', 'work_date'], 'attendance_records_company_user_date_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_records')) {
            return;
        }

        if (! Schema::hasColumn('attendance_records', 'company_id')) {
            return;
        }

        Schema::table('attendance_records', function (Blueprint $table): void {
            if ($this->indexExists('attendance_records', 'attendance_records_company_user_date_unique')) {
                $table->dropUnique('attendance_records_company_user_date_unique');
            }

            if (! $this->indexExists('attendance_records', 'attendance_records_user_id_work_date_unique')) {
                $table->unique(['user_id', 'work_date'], 'attendance_records_user_id_work_date_unique');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            $rows = DB::select(sprintf('PRAGMA index_list(%s)', DB::getPdo()->quote($table)));

            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $database = DB::getDatabaseName();
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $indexName]
        );

        return $rows !== [];
    }
};
