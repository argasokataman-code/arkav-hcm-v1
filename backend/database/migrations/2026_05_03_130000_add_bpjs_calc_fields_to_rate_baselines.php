<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahkan kolom kalkulasi BPJS ke hcm_bpjs_governance_rate_baselines:
 * - risk_category        : kategori risiko JKK (1–5), hanya relevan untuk program jkk/employer
 * - jp_salary_cap        : batas atas gaji untuk kalkulasi JP (default PP 45/2015)
 * - bpjs_kes_salary_cap  : batas atas gaji untuk kalkulasi BPJS Kesehatan (default Perpres 75/2019)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_bpjs_governance_rate_baselines', function (Blueprint $table): void {
            if (! Schema::hasColumn('hcm_bpjs_governance_rate_baselines', 'risk_category')) {
                $table->unsignedTinyInteger('risk_category')->nullable()->after('max_rate')
                    ->comment('JKK risk category 1–5 (PP 44/2015). Hanya relevan untuk program jkk porsi employer.');
            }
            if (! Schema::hasColumn('hcm_bpjs_governance_rate_baselines', 'jp_salary_cap')) {
                $table->unsignedBigInteger('jp_salary_cap')->nullable()->after('risk_category')
                    ->comment('Batas atas gaji untuk kalkulasi JP dalam Rupiah. Null = gunakan default sistem (PP 45/2015).');
            }
            if (! Schema::hasColumn('hcm_bpjs_governance_rate_baselines', 'bpjs_kes_salary_cap')) {
                $table->unsignedBigInteger('bpjs_kes_salary_cap')->nullable()->after('jp_salary_cap')
                    ->comment('Batas atas gaji untuk kalkulasi BPJS Kesehatan dalam Rupiah. Null = gunakan default sistem (Perpres 75/2019).');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hcm_bpjs_governance_rate_baselines', function (Blueprint $table): void {
            $table->dropColumn(['risk_category', 'jp_salary_cap', 'bpjs_kes_salary_cap']);
        });
    }
};
