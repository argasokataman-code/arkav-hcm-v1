<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_thr_batch_lines')) {
            return;
        }

        Schema::table('hcm_thr_batch_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('hcm_thr_batch_lines', 'thr_slip_public_no')) {
                $table->string('thr_slip_public_no', 48)->nullable()->after('id');
            }
        });

        if (! Schema::hasColumn('hcm_thr_batch_lines', 'thr_slip_public_no')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                'UPDATE hcm_thr_batch_lines AS l
                 INNER JOIN hcm_thr_batches AS b ON b.id = l.hcm_thr_batch_id
                 SET l.thr_slip_public_no = CONCAT(\'THR-\', b.calendar_year, \'-\', l.id)
                 WHERE l.thr_slip_public_no IS NULL OR l.thr_slip_public_no = \'\''
            );
        } else {
            $lineIds = DB::table('hcm_thr_batch_lines')->orderBy('id')->pluck('id');
            foreach ($lineIds as $lineId) {
                $batchId = DB::table('hcm_thr_batch_lines')->where('id', $lineId)->value('hcm_thr_batch_id');
                if ($batchId === null) {
                    continue;
                }
                $year = DB::table('hcm_thr_batches')->where('id', $batchId)->value('calendar_year');
                if ($year === null) {
                    continue;
                }
                DB::table('hcm_thr_batch_lines')->where('id', $lineId)->update([
                    'thr_slip_public_no' => 'THR-'.$year.'-'.$lineId,
                ]);
            }
        }

        try {
            DB::statement('ALTER TABLE hcm_thr_batch_lines ADD UNIQUE hcm_thr_batch_lines_thr_slip_public_no_unique (thr_slip_public_no)');
        } catch (\Exception $e) {
            // Index likely already exists
        }

        if ($driver === 'mysql' && ! DB::table('hcm_thr_batch_lines')->whereNull('thr_slip_public_no')->exists()) {
            try {
                DB::statement('ALTER TABLE hcm_thr_batch_lines MODIFY thr_slip_public_no VARCHAR(48) NOT NULL');
            } catch (\Exception $e) {}
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('hcm_thr_batch_lines')) {
            return;
        }

        Schema::table('hcm_thr_batch_lines', function (Blueprint $table) {
            if (Schema::hasColumn('hcm_thr_batch_lines', 'thr_slip_public_no')) {
                $table->dropUnique('hcm_thr_batch_lines_thr_slip_public_no_unique');
                $table->dropColumn('thr_slip_public_no');
            }
        });
    }
};
