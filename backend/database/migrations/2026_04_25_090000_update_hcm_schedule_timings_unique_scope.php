<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS hcm_schedule_timings_user_id_unique');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS hcm_schedule_timings_company_user_unique ON hcm_schedule_timings(company_id, user_id)');

            return;
        }

        Schema::table('hcm_schedule_timings', function (Blueprint $table) {
            try {
                $table->dropUnique('hcm_schedule_timings_user_id_unique');
            } catch (\Throwable) {
                // no-op when the legacy unique key does not exist
            }
        });

        Schema::table('hcm_schedule_timings', function (Blueprint $table) {
            try {
                $table->unique(['company_id', 'user_id'], 'hcm_schedule_timings_company_user_unique');
            } catch (\Throwable) {
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

        Schema::table('hcm_schedule_timings', function (Blueprint $table) {
            try {
                $table->dropUnique('hcm_schedule_timings_company_user_unique');
            } catch (\Throwable) {
                // no-op when key does not exist
            }
        });

        Schema::table('hcm_schedule_timings', function (Blueprint $table) {
            try {
                $table->unique('user_id', 'hcm_schedule_timings_user_id_unique');
            } catch (\Throwable) {
                // no-op when unique key already exists
            }
        });
    }
};
