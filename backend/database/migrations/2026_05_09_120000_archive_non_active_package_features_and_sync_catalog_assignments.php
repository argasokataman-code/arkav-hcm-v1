<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('packages') || ! Schema::hasTable('package_features')) {
            return;
        }

        Schema::create('package_feature_archives', function (Blueprint $table): void {
            $table->id();
            $table->string('package_uuid')->nullable();
            $table->string('package_code', 100)->nullable();
            $table->string('feature_code', 100);
            $table->string('feature_name', 150)->nullable();
            $table->integer('feature_limit')->nullable();
            $table->string('source_table', 100)->default('package_features');
            $table->text('archived_reason')->nullable();
            $table->timestamp('archived_at');
            $table->timestamps();

            $table->index(['feature_code', 'archived_at']);
            $table->index(['package_uuid', 'feature_code']);
        });

        $now = now();

        // Archive non-active package features that are intentionally retired from active package catalog.
        $retiredFeatureCodes = ['api_access', 'priority_support'];

        $rowsToArchive = DB::table('package_features as pf')
            ->leftJoin('packages as p', 'p.uuid', '=', 'pf.package_uuid')
            ->whereIn('pf.feature_code', $retiredFeatureCodes)
            ->select(
                'pf.package_uuid',
                'p.code as package_code',
                'pf.feature_code',
                'pf.feature_name',
                'pf.limit as feature_limit'
            )
            ->get();

        foreach ($rowsToArchive as $row) {
            DB::table('package_feature_archives')->insert([
                'package_uuid' => $row->package_uuid,
                'package_code' => $row->package_code,
                'feature_code' => $row->feature_code,
                'feature_name' => $row->feature_name,
                'feature_limit' => $row->feature_limit,
                'source_table' => 'package_features',
                'archived_reason' => 'Retired from active package catalog; kept as archive for potential future reuse.',
                'archived_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($rowsToArchive->isNotEmpty()) {
            DB::table('package_features')->whereIn('feature_code', $retiredFeatureCodes)->delete();
        }

        // Heal catalog features that should be present in package assignments.
        // Limits: 1 for baseline-enabled tiers, NULL for unlimited tiers, 0 for addon-disabled tiers.
        $featureNames = [
            'attendance_shift_scheduling' => 'Shift Scheduling',
            'leave_approval_flow' => 'Approval Workflow',
            'payroll_components' => 'Compensation Components',
            'payroll_thr' => 'THR Management',
            'employee_lifecycle' => 'Lifecycle Tracking',
            'performance_goal_tracking' => 'Advanced Goal Tracking',
            'notifications' => 'Notifications',
            'trial_billing_dashboard' => 'Trial Billing Dashboard',
            'tax_governance' => 'Tax Governance',
        ];

        $mvpEnabled = [
            'trial' => 1,
            'starter' => 1,
            'growth' => 1,
            'business' => 1,
            'enterprise' => null,
            'ultimate' => null,
            'unlimited' => null,
        ];

        $addonEnabled = [
            'trial' => 0,
            'starter' => 0,
            'growth' => 0,
            'business' => 1,
            'enterprise' => 1,
            'ultimate' => null,
            'unlimited' => null,
        ];

        $mvpFeatures = [
            'attendance_shift_scheduling',
            'leave_approval_flow',
            'payroll_components',
            'payroll_thr',
            'notifications',
            'trial_billing_dashboard',
            'tax_governance',
        ];

        $addonFeatures = [
            'employee_lifecycle',
            'performance_goal_tracking',
        ];

        $packages = DB::table('packages')->select('uuid', 'code')->get();

        foreach ($packages as $package) {
            foreach ($mvpFeatures as $code) {
                DB::table('package_features')->updateOrInsert(
                    [
                        'package_uuid' => (string) $package->uuid,
                        'feature_code' => $code,
                    ],
                    [
                        'feature_name' => $featureNames[$code],
                        'limit' => $mvpEnabled[$package->code] ?? 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            foreach ($addonFeatures as $code) {
                DB::table('package_features')->updateOrInsert(
                    [
                        'package_uuid' => (string) $package->uuid,
                        'feature_code' => $code,
                    ],
                    [
                        'feature_name' => $featureNames[$code],
                        'limit' => $addonEnabled[$package->code] ?? 0,
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

        if (Schema::hasTable('package_feature_archives')) {
            $rows = DB::table('package_feature_archives')
                ->whereIn('feature_code', ['api_access', 'priority_support'])
                ->orderBy('id')
                ->get();

            foreach ($rows as $row) {
                if (! $row->package_uuid) {
                    continue;
                }

                DB::table('package_features')->updateOrInsert(
                    [
                        'package_uuid' => (string) $row->package_uuid,
                        'feature_code' => (string) $row->feature_code,
                    ],
                    [
                        'feature_name' => $row->feature_name,
                        'limit' => $row->feature_limit,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            Schema::dropIfExists('package_feature_archives');
        }
    }
};
