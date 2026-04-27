<?php

use App\Models\HcmSalaryComponent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_salary_component_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->string('kind', 32); // addition|deduction
            $table->string('code', 64);
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['kind', 'code']);
            $table->index(['kind', 'is_active']);
        });

        $now = now();

        $labels = [
            'basic_wage' => 'Upah pokok',
            'fixed_allowance' => 'Tunjangan tetap',
            'common_allowance' => 'Tunjangan umum',
            'family_allowance' => 'Tunjangan keluarga',
            'irregular_allowance' => 'Tunjangan tidak tetap / insentif',
            'overtime' => 'Upah lembur',
            'thr' => 'THR',
            'bonus' => 'Bonus (luar THR)',
            'natura_taxable' => 'Natura kena pajak',
            'natura_non_taxable' => 'Natura tidak kena pajak',
            'special_allowance' => 'Tunjangan khusus / insidentil',
            'reimbursement' => 'Reimbursement',
            'termination_benefit' => 'Kompensasi terminasi',
            'employer_cost_display' => 'Beban perusahaan (info slip)',
            'other_addition' => 'Pendapatan lain',
            'bpjs_health_employee' => 'BPJS Kesehatan (pekerja)',
            'bpjs_jht_employee' => 'JHT (pekerja)',
            'bpjs_jp_employee' => 'JP / pensiun (pekerja)',
            'pension_employee' => 'Iuran pensiun pekerja',
            'pph21_ter' => 'PPh 21 — TER bulanan',
            'pph21_december_recon' => 'PPh 21 — rekonsiliasi',
            'other_statutory' => 'Potongan wajib lain',
            'internal_advance' => 'Kasbon / uang muka',
            'internal_loan' => 'Pinjaman internal',
            'internal_cooperative' => 'Koperasi / internal',
            'internal_other' => 'Potongan internal lain',
            'other_deduction' => 'Potongan lain',
        ];

        $rows = [];
        $order = 10;

        foreach (HcmSalaryComponent::ADDITION_CATEGORIES as $code) {
            $rows[] = [
                'kind' => 'addition',
                'code' => $code,
                'name' => $labels[$code] ?? $code,
                'description' => null,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $order += 10;
        }

        // Optional category for better readability in UI and future custom mapping.
        $rows[] = [
            'kind' => 'addition',
            'code' => 'common_allowance',
            'name' => 'Tunjangan umum',
            'description' => 'Kategori tambahan yang bisa dipakai untuk tunjangan umum perusahaan.',
            'is_system' => true,
            'is_active' => true,
            'sort_order' => $order,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $order += 10;

        $rows[] = [
            'kind' => 'addition',
            'code' => 'family_allowance',
            'name' => 'Tunjangan keluarga',
            'description' => 'Kategori tambahan untuk allowance berbasis status keluarga/tanggungan.',
            'is_system' => true,
            'is_active' => true,
            'sort_order' => $order,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $order = 10;
        foreach (HcmSalaryComponent::DEDUCTION_CATEGORIES as $code) {
            $rows[] = [
                'kind' => 'deduction',
                'code' => $code,
                'name' => $labels[$code] ?? $code,
                'description' => null,
                'is_system' => true,
                'is_active' => true,
                'sort_order' => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $order += 10;
        }

        DB::table('hcm_salary_component_categories')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_salary_component_categories');
    }
};
