<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix package feature and add-on anomalies found in audit (2026-05-12):
 *
 *  Critical #1  — Delete holiday_calendar from package_addons (it is a built-in
 *                 feature in ALL packages via backfill migration; selling it as
 *                 an add-on means tenants can pay twice for the same feature).
 *
 *  Critical #2  — Starter payroll limit=0 → 1  (payroll is MVP; Starter tenants
 *                 pay Rp 199 000/month and should access payroll).
 *
 *  Critical #3  — Trial payroll limit=0 → 1  (trial must showcase MVP features
 *                 for conversion; tickets already enabled but payroll was not).
 *
 *  Critical #4  — Enterprise max_employees limit=20 → NULL (unlimited) — logical
 *                 bug: the most expensive package cannot have fewer employees than
 *                 Starter (50).
 *
 *  Critical #5  — Ultimate is missing goal_tracking, training,
 *                 employee_document_center that Business and Enterprise have.
 *                 Insert/update with limit=NULL (unlimited) for Ultimate.
 *
 *  Extra         — Add 3 MVP features missing from all packages:
 *                  notifications, tax_governance, trial_billing_dashboard.
 *                  Added to all non-internal packages (trial → ultimate).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('packages') || ! Schema::hasTable('package_features')) {
            return;
        }

        $now = now();

        // ──────────────────────────────────────────────────────────────────────
        // Critical #1 — Remove holiday_calendar from add-on catalog
        // ──────────────────────────────────────────────────────────────────────
        if (Schema::hasTable('package_addons')) {
            DB::table('package_addons')
                ->where('code', 'holiday_calendar')
                ->delete();
        }

        // ──────────────────────────────────────────────────────────────────────
        // Critical #2 — Starter: enable payroll (limit 0 → 1)
        // ──────────────────────────────────────────────────────────────────────
        $starterUuid = DB::table('packages')->where('code', 'starter')->value('uuid');
        if ($starterUuid) {
            DB::table('package_features')
                ->where('package_uuid', $starterUuid)
                ->where('feature_code', 'payroll')
                ->where('limit', 0)
                ->update(['limit' => 1, 'updated_at' => $now]);
        }

        // ──────────────────────────────────────────────────────────────────────
        // Critical #3 — Trial: enable payroll (limit 0 → 1)
        // ──────────────────────────────────────────────────────────────────────
        $trialUuid = DB::table('packages')->where('code', 'trial')->value('uuid');
        if ($trialUuid) {
            DB::table('package_features')
                ->where('package_uuid', $trialUuid)
                ->where('feature_code', 'payroll')
                ->where('limit', 0)
                ->update(['limit' => 1, 'updated_at' => $now]);
        }

        // ──────────────────────────────────────────────────────────────────────
        // Critical #4 — Enterprise: max_employees limit=20 → NULL (unlimited)
        // ──────────────────────────────────────────────────────────────────────
        $enterpriseUuid = DB::table('packages')->where('code', 'enterprise')->value('uuid');
        if ($enterpriseUuid) {
            DB::table('package_features')
                ->where('package_uuid', $enterpriseUuid)
                ->where('feature_code', 'max_employees')
                ->where('limit', 20)
                ->update(['limit' => null, 'updated_at' => $now]);
        }

        // ──────────────────────────────────────────────────────────────────────
        // Critical #5 — Ultimate: add missing goal_tracking, training,
        //               employee_document_center (all unlimited)
        // ──────────────────────────────────────────────────────────────────────
        $ultimateUuid = DB::table('packages')->where('code', 'ultimate')->value('uuid');
        if ($ultimateUuid) {
            foreach (['goal_tracking', 'training', 'employee_document_center'] as $featureCode) {
                $names = [
                    'goal_tracking' => 'Goal Tracking',
                    'training' => 'Training',
                    'employee_document_center' => 'Employee Document Center',
                ];
                DB::table('package_features')->updateOrInsert(
                    ['package_uuid' => $ultimateUuid, 'feature_code' => $featureCode],
                    [
                        'feature_name' => $names[$featureCode],
                        'limit' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        // ──────────────────────────────────────────────────────────────────────
        // Extra — Add 3 missing MVP features to all public packages
        //         notifications, tax_governance, trial_billing_dashboard
        //
        //  Package tiers and intended limits:
        //    trial / starter              → limit = 1
        //    growth / business            → limit = 1
        //    enterprise / ultimate        → limit = NULL (unlimited)
        //    unlimited (global-admin-only) → limit = NULL
        // ──────────────────────────────────────────────────────────────────────
        $mvpPlatformFeatures = [
            'notifications' => 'Notifications',
            'tax_governance' => 'Tax Governance',
            'trial_billing_dashboard' => 'Trial Billing Dashboard',
        ];

        $limitsPerPackage = [
            'trial' => 1,
            'starter' => 1,
            'growth' => 1,
            'business' => 1,
            'enterprise' => null,
            'ultimate' => null,
            'unlimited' => null,
        ];

        $publicPackages = DB::table('packages')
            ->select('uuid', 'code')
            ->get();

        foreach ($publicPackages as $pkg) {
            // Use array_key_exists instead of ?? to preserve intentional null limits
            $limit = array_key_exists($pkg->code, $limitsPerPackage)
                ? $limitsPerPackage[$pkg->code]
                : 1;
            foreach ($mvpPlatformFeatures as $code => $name) {
                DB::table('package_features')->updateOrInsert(
                    ['package_uuid' => (string) $pkg->uuid, 'feature_code' => $code],
                    [
                        'feature_name' => $name,
                        'limit' => $limit,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('packages') || ! Schema::hasTable('package_features')) {
            return;
        }

        $now = now();

        // Reverse Critical #2 — Starter payroll back to 0
        $starterUuid = DB::table('packages')->where('code', 'starter')->value('uuid');
        if ($starterUuid) {
            DB::table('package_features')
                ->where('package_uuid', $starterUuid)
                ->where('feature_code', 'payroll')
                ->update(['limit' => 0, 'updated_at' => $now]);
        }

        // Reverse Critical #3 — Trial payroll back to 0
        $trialUuid = DB::table('packages')->where('code', 'trial')->value('uuid');
        if ($trialUuid) {
            DB::table('package_features')
                ->where('package_uuid', $trialUuid)
                ->where('feature_code', 'payroll')
                ->update(['limit' => 0, 'updated_at' => $now]);
        }

        // Reverse Critical #4 — Enterprise max_employees back to 20
        $enterpriseUuid = DB::table('packages')->where('code', 'enterprise')->value('uuid');
        if ($enterpriseUuid) {
            DB::table('package_features')
                ->where('package_uuid', $enterpriseUuid)
                ->where('feature_code', 'max_employees')
                ->whereNull('limit')
                ->update(['limit' => 20, 'updated_at' => $now]);
        }

        // Note: Critical #1 (addon deleted) and Critical #5 (features added) are
        // not reversed here to avoid data loss on production rollback. Re-run the
        // seeder to restore add-on catalog if needed.
    }
};
