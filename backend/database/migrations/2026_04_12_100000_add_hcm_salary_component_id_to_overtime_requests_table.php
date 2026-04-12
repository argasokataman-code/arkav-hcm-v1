<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->foreignId('hcm_salary_component_id')
                ->nullable()
                ->after('hcm_overtime_type_id')
                ->constrained('hcm_salary_components')
                ->nullOnDelete();
        });

        $cid = DB::table('hcm_salary_components')->where('code', 'upah_lembur')->value('id');
        if ($cid) {
            DB::table('overtime_requests')->whereNull('hcm_salary_component_id')->update(['hcm_salary_component_id' => $cid]);
        }
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hcm_salary_component_id');
        });
    }
};
