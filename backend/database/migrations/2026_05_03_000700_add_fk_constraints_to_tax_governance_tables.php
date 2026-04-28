<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only add FK constraints on databases that support them reliably (not SQLite).
        // SQLite does not enforce FK constraints by default and ALTER TABLE ADD CONSTRAINT is unsupported.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // AN-005: Add FK constraints to enforce referential integrity on tax governance tables.
        //
        // SCHEMA NOTE (2026-05-03): `companies` and `users` tables use `uuid` as their PRIMARY KEY.
        // Their `id` column is a legacy bigint auto-increment with only a btree index (not UNIQUE/PK).
        // MariaDB/MySQL requires the referenced column to be PRIMARY KEY or UNIQUE for FKs.
        // FKs referencing companies.id and users.id are therefore SKIPPED — they would fail on MariaDB.
        //
        // Only the policy_events → policies FK is enforced (hcm_tax_governance_policies uses id as PK).

        // hcm_tax_governance_policy_events.hcm_tax_governance_policy_id → hcm_tax_governance_policies(id)
        if (Schema::hasTable('hcm_tax_governance_policy_events') && Schema::hasTable('hcm_tax_governance_policies')) {
            Schema::table('hcm_tax_governance_policy_events', function (Blueprint $table): void {
                $table->foreign('hcm_tax_governance_policy_id', 'fk_tax_policy_event_policy')
                    ->references('id')->on('hcm_tax_governance_policies')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('hcm_tax_governance_policy_events')) {
            try {
                Schema::table('hcm_tax_governance_policy_events', function (Blueprint $table): void {
                    $table->dropForeign('fk_tax_policy_event_policy');
                });
            } catch (\Throwable) {
                // FK may not exist if migration was partial
            }
        }
    }
};
