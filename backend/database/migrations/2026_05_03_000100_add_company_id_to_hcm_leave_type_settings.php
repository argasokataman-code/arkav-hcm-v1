<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add company_id to hcm_leave_type_settings and hcm_leave_custom_policies
 * to enforce per-tenant isolation (multi-tenant RBAC baseline).
 *
 * Existing global rows are assigned company_id = NULL (global defaults).
 * Each tenant manages their own leave type rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hcm_leave_type_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id')->index();

            // Drop the global unique constraint on code alone;
            // replace with per-company uniqueness: (company_id, code).
            $table->dropUnique(['code']);
            $table->unique(['company_id', 'code'], 'hcm_leave_type_settings_company_code_unique');
        });

        Schema::table('hcm_leave_custom_policies', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('hcm_leave_type_settings', function (Blueprint $table) {
            $table->dropUnique('hcm_leave_type_settings_company_code_unique');
            $table->unique('code');
            $table->dropColumn('company_id');
        });

        Schema::table('hcm_leave_custom_policies', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
    }
};
