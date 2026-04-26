<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hcm_terminations')) {
            return;
        }

        Schema::table('hcm_terminations', function (Blueprint $table): void {
            if (! Schema::hasColumn('hcm_terminations', 'termination_reason_code')) {
                $table->string('termination_reason_code', 64)->nullable()->after('termination_type');
            }
            if (! Schema::hasColumn('hcm_terminations', 'legal_basis_code')) {
                $table->string('legal_basis_code', 64)->nullable()->after('termination_reason_code');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hcm_terminations')) {
            return;
        }

        Schema::table('hcm_terminations', function (Blueprint $table): void {
            foreach (['legal_basis_code', 'termination_reason_code'] as $column) {
                if (Schema::hasColumn('hcm_terminations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
