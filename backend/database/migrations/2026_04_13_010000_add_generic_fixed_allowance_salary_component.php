<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $row = [
            'code' => 'tunjangan_tetap',
            'name' => 'Tunjangan Tetap (Payroll Bulanan)',
            'description' => 'Bucket generik untuk nominal tunjangan tetap bulanan dari Employee Salary / compensation profile.',
            'kind' => 'addition',
            'category' => 'fixed_allowance',
            'legal_basis' => 'Perjanjian kerja / PKB / kebijakan perusahaan.',
            'legal_notes' => 'Dipakai sebagai label agregat tunjangan tetap dari profil kompensasi. Rincian tunjangan tetap spesifik tetap bisa dikelola sebagai master terpisah.',
            'include_bpjs_health_wage_base' => true,
            'include_bpjs_tk_wage_base' => true,
            'include_thr_calculation_base' => true,
            'include_pph21_ter_gross' => true,
            'include_pph21_annual_reconciliation' => true,
            'subject_overtime_regulation' => false,
            'affects_net_pay' => true,
            'employer_cost_line' => false,
            'is_system_locked' => true,
            'sort_order' => 25,
            'is_active' => true,
        ];

        $exists = DB::table('hcm_salary_components')->where('code', $row['code'])->exists();
        if ($exists) {
            DB::table('hcm_salary_components')
                ->where('code', $row['code'])
                ->update(array_merge($row, ['updated_at' => $now]));

            return;
        }

        DB::table('hcm_salary_components')->insert(array_merge($row, [
            'created_at' => $now,
            'updated_at' => $now,
        ]));
    }

    public function down(): void
    {
        DB::table('hcm_salary_components')->where('code', 'tunjangan_tetap')->delete();
    }
};
