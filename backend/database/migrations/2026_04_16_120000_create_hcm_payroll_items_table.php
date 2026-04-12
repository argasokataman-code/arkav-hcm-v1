<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hcm_payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hcm_salary_component_id')->nullable()->unique()->constrained('hcm_salary_components')->nullOnDelete();
            $table->string('code', 64)->nullable();
            $table->string('name', 200);
            $table->string('kind', 32);
            $table->string('category', 64);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $codes = ['upah_pokok', 'tunjangan_tetap_transport', 'upah_lembur'];
        foreach ($codes as $code) {
            $row = DB::table('hcm_salary_components')->where('code', $code)->first();
            if ($row === null) {
                continue;
            }
            DB::table('hcm_payroll_items')->insert([
                'hcm_salary_component_id' => $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'kind' => $row->kind,
                'category' => $row->category,
                'notes' => 'Dibuat dari master komponen gaji (seed).',
                'sort_order' => (int) $row->sort_order,
                'is_active' => (bool) $row->is_active,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('hcm_payroll_items')->insert([
            'hcm_salary_component_id' => null,
            'code' => 'tunjangan_proyek_internal',
            'name' => 'Tunjangan proyek (contoh payroll item)',
            'kind' => 'addition',
            'category' => 'irregular_allowance',
            'notes' => 'Contoh baris **tanpa** tautan master — dual source untuk review produk.',
            'sort_order' => 950,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('hcm_payroll_items');
    }
};
