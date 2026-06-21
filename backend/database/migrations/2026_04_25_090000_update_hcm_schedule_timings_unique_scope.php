<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function hasIndex(string $table, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS hcm_schedule_timings_user_id_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS hcm_schedule_timings_company_user_unique ON hcm_schedule_timings(company_id, user_id)');

            return;
        }

        // FK on user_id may depend on this index name in MySQL; ensure replacement index exists first.
        if (! $this->hasIndex('hcm_schedule_timings', 'hcm_schedule_timings_user_id_idx')) {
            Schema::table('hcm_schedule_timings', function (Blueprint $table) {
                $table->index('user_id', 'hcm_schedule_timings_user_id_idx');
            });
        }

        Schema::table('hcm_schedule_timings', function (Blueprint $table) {
            try {
                $table->dropUnique('hcm_schedule_timings_user_id_unique');
            } catch (Throwable) {
                // no-op when the legacy unique key does not exist
            }
        });

        Schema::table('hcm_schedule_timings', function (Blueprint $table) {
            try {
                $table->unique(['company_id', 'user_id'], 'hcm_schedule_timings_company_user_unique');
            } catch (Throwable) {
                // no-op when unique key already exists
            }
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS hcm_schedule_timings_company_user_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS hcm_schedule_timings_user_id_unique ON hcm_schedule_timings(user_id)');

            return;
        }

        // Remove helper index only if present; keep migration idempotent.
        if ($this->hasIndex('hcm_schedule_timings', 'hcm_schedule_timings_user_id_idx')) {
            Schema::table('hcm_schedule_timings', function (Blueprint $table) {
                try {
                    $table->dropIndex('hcm_schedule_timings_user_id_idx');
                } catch (Throwable) {
                    // no-op when helper index is already absent
                }
            });
        }

        Schema::table('hcm_schedule_timings', function (Blueprint $table) {
            try {
                $table->dropUnique('hcm_schedule_timings_company_user_unique');
            } catch (Throwable) {
                // no-op when key does not exist
            }
        });

        Schema::table('hcm_schedule_timings', function (Blueprint $table) {
            try {
                $table->unique('user_id', 'hcm_schedule_timings_user_id_unique');
            } catch (Throwable) {
                // no-op when unique key already exists
            }
        });
    }
};
