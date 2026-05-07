<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add FK constraints that were missing from privacy/compliance tables.
     *
     * Context: companies/users/employee_profiles already migrated to UUID as PK.
     * Their legacy integer `id` columns are non-unique indexes only, so FK to `id`
     * is impossible. This migration:
     *   1. Adds `company_uuid` (char 36) to tables that had only `company_id`
     *   2. Backfills `company_uuid` from the companies table
     *   3. Adds FK constraints to uuid-based PKs
     *
     * Tables covered:
     *  - employee_ai_consents       → FK employee_uuid → employee_profiles.uuid
     *                               → FK user_uuid     → users.uuid
     *  - employee_biometric_consents → add company_uuid + FK, FK employee_uuid
     *  - erasure_requests            → add company_uuid + FK
     *  - export_audit_logs           → add company_uuid + FK, FK user_uuid
     *  - data_breach_incidents       → add company_uuid + FK
     *  - hcm_activity_logs           → add company_uuid + FK
     */
    public function up(): void
    {
        // ── 1. employee_ai_consents ─────────────────────────────────────────
        Schema::table('employee_ai_consents', function (Blueprint $table): void {
            $table->foreign('employee_uuid', 'eac_employee_uuid_fk')
                ->references('uuid')->on('employee_profiles')
                ->onDelete('cascade');

            $table->foreign('user_uuid', 'eac_user_uuid_fk')
                ->references('uuid')->on('users')
                ->onDelete('set null');
        });

        // ── 2. employee_biometric_consents ──────────────────────────────────
        Schema::table('employee_biometric_consents', function (Blueprint $table): void {
            $table->char('company_uuid', 36)->nullable()->after('company_id');
        });
        DB::statement('UPDATE employee_biometric_consents ebc
            JOIN companies c ON c.id = ebc.company_id
            SET ebc.company_uuid = c.uuid');
        Schema::table('employee_biometric_consents', function (Blueprint $table): void {
            $table->foreign('employee_uuid', 'ebc_employee_uuid_fk')
                ->references('uuid')->on('employee_profiles')
                ->onDelete('cascade');
            $table->foreign('company_uuid', 'ebc_company_uuid_fk')
                ->references('uuid')->on('companies')
                ->onDelete('cascade');
        });

        // ── 3. erasure_requests ─────────────────────────────────────────────
        Schema::table('erasure_requests', function (Blueprint $table): void {
            $table->char('company_uuid', 36)->nullable()->after('company_id');
        });
        DB::statement('UPDATE erasure_requests er
            JOIN companies c ON c.id = er.company_id
            SET er.company_uuid = c.uuid');
        Schema::table('erasure_requests', function (Blueprint $table): void {
            $table->foreign('company_uuid', 'er_company_uuid_fk')
                ->references('uuid')->on('companies')
                ->onDelete('cascade');
        });

        // ── 4. export_audit_logs ────────────────────────────────────────────
        Schema::table('export_audit_logs', function (Blueprint $table): void {
            $table->char('company_uuid', 36)->nullable()->after('company_id');
        });
        DB::statement('UPDATE export_audit_logs eal
            JOIN companies c ON c.id = eal.company_id
            SET eal.company_uuid = c.uuid');
        Schema::table('export_audit_logs', function (Blueprint $table): void {
            $table->foreign('user_uuid', 'eal_user_uuid_fk')
                ->references('uuid')->on('users')
                ->onDelete('cascade');
            $table->foreign('company_uuid', 'eal_company_uuid_fk')
                ->references('uuid')->on('companies')
                ->onDelete('cascade');
        });

        // ── 5. data_breach_incidents ────────────────────────────────────────
        Schema::table('data_breach_incidents', function (Blueprint $table): void {
            $table->char('company_uuid', 36)->nullable()->after('company_id');
        });
        DB::statement('UPDATE data_breach_incidents dbi
            JOIN companies c ON c.id = dbi.company_id
            SET dbi.company_uuid = c.uuid
            WHERE dbi.company_id IS NOT NULL');
        Schema::table('data_breach_incidents', function (Blueprint $table): void {
            $table->foreign('company_uuid', 'dbi_company_uuid_fk')
                ->references('uuid')->on('companies')
                ->onDelete('set null');
        });

        // ── 6. hcm_activity_logs ────────────────────────────────────────────
        Schema::table('hcm_activity_logs', function (Blueprint $table): void {
            $table->char('company_uuid', 36)->nullable()->after('company_id');
        });
        DB::statement('UPDATE hcm_activity_logs hal
            JOIN companies c ON c.id = hal.company_id
            SET hal.company_uuid = c.uuid');
        Schema::table('hcm_activity_logs', function (Blueprint $table): void {
            $table->foreign('company_uuid', 'hal_company_uuid_fk')
                ->references('uuid')->on('companies')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('employee_ai_consents', function (Blueprint $table): void {
            $table->dropForeign('eac_employee_uuid_fk');
            $table->dropForeign('eac_user_uuid_fk');
        });

        Schema::table('employee_biometric_consents', function (Blueprint $table): void {
            $table->dropForeign('ebc_employee_uuid_fk');
            $table->dropForeign('ebc_company_uuid_fk');
            $table->dropColumn('company_uuid');
        });

        Schema::table('erasure_requests', function (Blueprint $table): void {
            $table->dropForeign('er_company_uuid_fk');
            $table->dropColumn('company_uuid');
        });

        Schema::table('export_audit_logs', function (Blueprint $table): void {
            $table->dropForeign('eal_user_uuid_fk');
            $table->dropForeign('eal_company_uuid_fk');
            $table->dropColumn('company_uuid');
        });

        Schema::table('data_breach_incidents', function (Blueprint $table): void {
            $table->dropForeign('dbi_company_uuid_fk');
            $table->dropColumn('company_uuid');
        });

        Schema::table('hcm_activity_logs', function (Blueprint $table): void {
            $table->dropForeign('hal_company_uuid_fk');
            $table->dropColumn('company_uuid');
        });
    }
};
