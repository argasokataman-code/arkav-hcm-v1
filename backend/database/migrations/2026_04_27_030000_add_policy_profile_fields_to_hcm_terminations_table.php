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
            if (! Schema::hasColumn('hcm_terminations', 'policy_profile_key')) {
                $table->string('policy_profile_key', 64)->nullable()->after('legal_basis_code');
            }
            if (! Schema::hasColumn('hcm_terminations', 'policy_formula_version')) {
                $table->string('policy_formula_version', 32)->nullable()->after('policy_profile_key');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hcm_terminations')) {
            return;
        }

        Schema::table('hcm_terminations', function (Blueprint $table): void {
            foreach (['policy_formula_version', 'policy_profile_key'] as $column) {
                if (Schema::hasColumn('hcm_terminations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
