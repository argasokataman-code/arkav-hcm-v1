<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix addon catalog duplicates (2026-05-27):
 *
 *  Problem: Several feature codes exist in BOTH `package_features` (bundled in
 *  packages) AND `package_addons` (sold as purchasable add-ons). This means
 *  tenants see purchasable add-ons for features they already have, which is
 *  confusing and incorrect.
 *
 *  Critical #1 — `tickets` is in ALL active packages. Remove from package_addons.
 *
 *  Critical #2 — `attendance_shift_scheduling` and `leave_approval_flow` are MVP
 *  features intended for all packages (per migration 2026_05_09_120000) but were
 *  not backfilled to enterprise, ultimate, growth, business packages. Backfill
 *  them to all missing packages, then remove from package_addons.
 *
 *  After this migration:
 *  - The add-on catalog will no longer show features that are already bundled
 *    in every package.
 *  - The addon catalog stays dynamic: features truly exclusive to add-ons are
 *    still purchasable (asset_management, employee_document_center, etc.).
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $mvpFeaturesToBackfill = [
        'attendance_shift_scheduling',
        'leave_approval_flow',
    ];

    /** @var list<string> */
    private array $featuresToRemoveFromAddonCatalog = [
        'tickets',
        'attendance_shift_scheduling',
        'leave_approval_flow',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('packages') || ! Schema::hasTable('package_features') || ! Schema::hasTable('package_addons')) {
            return;
        }

        $now = now();

        // ─────────────────────────────────────────────────────────────────────
        // Step 1 — Backfill attendance_shift_scheduling and leave_approval_flow
        // to ALL active packages that are currently missing them.
        // These are MVP features (core HCM) that every plan should include.
        // ─────────────────────────────────────────────────────────────────────
        $featureNames = [
            'attendance_shift_scheduling' => 'Shift Scheduling',
            'leave_approval_flow' => 'Leave Approval Flow',
        ];

        $allPackages = DB::table('packages')
            ->where('status', 'active')
            ->select('uuid', 'code')
            ->get();

        foreach ($allPackages as $package) {
            foreach ($this->mvpFeaturesToBackfill as $featureCode) {
                $exists = DB::table('package_features')
                    ->where('package_uuid', $package->uuid)
                    ->where('feature_code', $featureCode)
                    ->exists();

                if (! $exists) {
                    DB::table('package_features')->insert([
                        'package_uuid' => $package->uuid,
                        'feature_code' => $featureCode,
                        'feature_name' => $featureNames[$featureCode],
                        'limit' => null, // unlimited
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // ─────────────────────────────────────────────────────────────────────
        // Step 2 — Remove duplicated features from package_addons.
        // These features are now present in every package so they should not
        // be sold as standalone add-ons.
        // ─────────────────────────────────────────────────────────────────────
        DB::table('package_addons')
            ->whereIn('code', $this->featuresToRemoveFromAddonCatalog)
            ->delete();
    }

    public function down(): void
    {
        // Re-insert the removed add-on entries so rollback is clean.
        // Limits are approximate; exact values can be restored from the original
        // package_addons seed if needed.
        $now = now();

        $addons = [
            [
                'code' => 'tickets',
                'name' => 'Tickets',
                'description' => 'Sistem tiket internal untuk pelaporan masalah dan permintaan.',
                'price_per_unit' => 49000,
                'unit_name' => 'tenant / month',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'attendance_shift_scheduling',
                'name' => 'Shift Scheduling',
                'description' => 'Penjadwalan shift kerja dan rotasi jadwal karyawan.',
                'price_per_unit' => 49000,
                'unit_name' => 'tenant / month',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'leave_approval_flow',
                'name' => 'Leave Approval Flow',
                'description' => 'Alur persetujuan cuti multi-level.',
                'price_per_unit' => 49000,
                'unit_name' => 'tenant / month',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($addons as $addon) {
            DB::table('package_addons')
                ->insertOrIgnore($addon);
        }
    }
};
