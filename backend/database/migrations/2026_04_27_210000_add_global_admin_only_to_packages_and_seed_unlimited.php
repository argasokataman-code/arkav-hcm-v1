<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('packages')) {
            return;
        }

        if (! Schema::hasColumn('packages', 'is_global_admin_only')) {
            Schema::table('packages', function (Blueprint $table): void {
                $table->boolean('is_global_admin_only')->default(false)->after('status');
                $table->index('is_global_admin_only', 'packages_is_global_admin_only_idx');
            });
        }

        $now = now();
        $packageUuid = (string) (DB::table('packages')->where('code', 'unlimited')->value('uuid') ?? '');

        if ($packageUuid === '') {
            $packageUuid = (string) Str::uuid();
            DB::table('packages')->insert([
                'uuid' => $packageUuid,
                'code' => 'unlimited',
                'name' => 'Unlimited (Global Admin)',
                'description' => 'Paket internal khusus global super admin. Tidak ditampilkan ke katalog publik.',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'billing_unit' => 'company',
                'status' => 'active',
                'is_global_admin_only' => true,
                'color' => '#111827',
                'sort_order' => 999,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('packages')
                ->where('uuid', $packageUuid)
                ->update([
                    'name' => 'Unlimited (Global Admin)',
                    'description' => 'Paket internal khusus global super admin. Tidak ditampilkan ke katalog publik.',
                    'status' => 'active',
                    'is_global_admin_only' => true,
                    'sort_order' => 999,
                    'updated_at' => $now,
                ]);
        }

        if (! Schema::hasTable('package_features') || ! Schema::hasColumn('package_features', 'package_uuid')) {
            return;
        }

        $featureTemplate = [
            'employee_management' => 'Employee Management',
            'attendance' => 'Attendance',
            'leave_management' => 'Leave Management',
            'payroll' => 'Payroll',
            'performance' => 'Performance',
            'training' => 'Training',
            'goal_tracking' => 'Goal Tracking',
            'asset_management' => 'Asset Management',
            'api_access' => 'API Access',
            'priority_support' => 'Priority Support',
            'tickets' => 'Tickets',
        ];

        foreach ($featureTemplate as $featureCode => $featureName) {
            DB::table('package_features')->updateOrInsert(
                [
                    'package_uuid' => $packageUuid,
                    'feature_code' => $featureCode,
                ],
                [
                    'feature_name' => $featureName,
                    'limit' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        // Forward-only migration: keep seeded package integrity for existing subscriptions.
    }
};
