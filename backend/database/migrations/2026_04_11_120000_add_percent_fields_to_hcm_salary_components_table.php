<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_salary_components', function (Blueprint $table) {
            $table->decimal('default_percent', 8, 4)->nullable()->after('legal_notes');
            $table->string('percent_basis', 64)->nullable()->after('default_percent');
        });

        // Tarif ilustratif — wajib disesuaikan dengan aturan BPJS/tarif berlaku per tenant.
        $rates = [
            'iuran_bpjs_kes_pekerja' => ['p' => '1.0000', 'b' => 'wage_bpjs_health'],
            'iuran_jht_pekerja' => ['p' => '2.0000', 'b' => 'wage_bpjs_tk'],
            'iuran_jp_pekerja' => ['p' => '1.0000', 'b' => 'wage_bpjs_tk'],
            'iuran_jht_pk' => ['p' => '3.7000', 'b' => 'wage_bpjs_tk'],
            'iuran_jp_pk' => ['p' => '2.0000', 'b' => 'wage_bpjs_tk'],
            'iuran_bpjs_kes_pk' => ['p' => '4.0000', 'b' => 'wage_bpjs_health'],
        ];

        foreach ($rates as $code => $r) {
            DB::table('hcm_salary_components')->where('code', $code)->update([
                'default_percent' => $r['p'],
                'percent_basis' => $r['b'],
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('hcm_salary_components', function (Blueprint $table) {
            $table->dropColumn(['default_percent', 'percent_basis']);
        });
    }
};
