<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $row = [
            'code' => 'kompensasi_pkwt',
            'name' => 'Kompensasi PKWT',
            'description' => 'Kompensasi berakhirnya hubungan kerja PKWT sesuai PP 35/2021.',
            'kind' => 'addition',
            'category' => 'other_addition',
            'legal_basis' => 'PP No. 35 Tahun 2021 tentang PKWT, alih daya, waktu kerja dan waktu istirahat, dan PHK.',
            'legal_notes' => 'Dibayar saat kontrak PKWT selesai. Bukan payroll bulanan reguler dan tidak dibayarkan tiap bulan.',
            'include_bpjs_health_wage_base' => false,
            'include_bpjs_tk_wage_base' => false,
            'include_thr_calculation_base' => false,
            'include_pph21_ter_gross' => true,
            'include_pph21_annual_reconciliation' => true,
            'subject_overtime_regulation' => false,
            'affects_net_pay' => true,
            'employer_cost_line' => false,
            'is_system_locked' => true,
            'sort_order' => 135,
            'is_active' => true,
        ];

        $exists = DB::table('hcm_salary_components')->where('code', $row['code'])->exists();
        if ($exists) {
            DB::table('hcm_salary_components')->where('code', $row['code'])->update(array_merge($row, [
                'updated_at' => $now,
            ]));

            return;
        }

        DB::table('hcm_salary_components')->insert(array_merge($row, [
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    public function down(): void
    {
        DB::table('hcm_salary_components')->where('code', 'kompensasi_pkwt')->delete();
    }
};
