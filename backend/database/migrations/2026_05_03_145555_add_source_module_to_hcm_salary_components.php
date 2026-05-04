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
    /**
     * Valid source_module values (enforced by application, not DB enum to allow future extension):
     * 'bpjs', 'allowance', 'pph21', 'overtime', 'thr', 'pkwt', 'system', null (tenant-custom)
     */
    public function up(): void
    {
        Schema::table('hcm_salary_components', function (Blueprint $table) {
            $table->string('source_module', 32)->nullable()->after('is_system_locked')->index();
        });

        // Backfill source_module based on known codes
        $bpjsCodes = ['iuran_bpjs_kes_pk','iuran_jht_pk','iuran_jp_pk','premi_jkk_pk','premi_jkm_pk',
                      'iuran_bpjs_kes_pekerja','iuran_jht_pekerja','iuran_jp_pekerja'];
        // NOTE: legacy template codes (tunjangan_tetap, tunjangan_tetap_jabatan,
        // tunjangan_tetap_transport, tunjangan_tidak_tetap, uang_makan_tetap)
        // are intentionally NOT tagged as source_module='allowance'. They are
        // generic system templates, not rows from Allowance Governance module.
        // Tagging them as 'allowance' caused them to leak into employee-salary
        // assignment dropdown alongside real governance items.
        $allowanceCodes = [];
        $pph21Codes = ['pph21_ter','pph21_rekonsiliasi'];
        $overtimeCodes = ['upah_lembur'];
        $thrCodes = ['thr'];
        $pkwtCodes = ['kompensasi_pkwt'];
        $systemCodes = ['upah_pokok','bonus'];

        DB::table('hcm_salary_components')
            ->whereIn('code', $bpjsCodes)
            ->update(['source_module' => 'bpjs']);

        DB::table('hcm_salary_components')
            ->whereIn('code', $allowanceCodes)
            ->update(['source_module' => 'allowance']);

        DB::table('hcm_salary_components')
            ->whereIn('code', $pph21Codes)
            ->update(['source_module' => 'pph21']);

        DB::table('hcm_salary_components')
            ->whereIn('code', $overtimeCodes)
            ->update(['source_module' => 'overtime']);

        DB::table('hcm_salary_components')
            ->whereIn('code', $thrCodes)
            ->update(['source_module' => 'thr']);

        DB::table('hcm_salary_components')
            ->whereIn('code', $pkwtCodes)
            ->update(['source_module' => 'pkwt']);

        DB::table('hcm_salary_components')
            ->whereIn('code', $systemCodes)
            ->update(['source_module' => 'system']);
    }

    public function down(): void
    {
        Schema::table('hcm_salary_components', function (Blueprint $table) {
            $table->dropIndex(['source_module']);
            $table->dropColumn('source_module');
        });
    }
};
