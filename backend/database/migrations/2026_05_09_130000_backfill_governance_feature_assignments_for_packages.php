<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('packages') || ! Schema::hasTable('package_features')) {
            return;
        }

        $featureNames = [
            'allowance_governance' => 'Allowance Governance',
            'bpjs_governance' => 'BPJS Governance',
            'spt_masa_pph21' => 'SPT Masa PPh 21',
        ];

        // Add-on tier defaults:
        // - trial/starter/growth: disabled by default (0)
        // - business/enterprise: enabled (1)
        // - ultimate/unlimited: unlimited (NULL)
        $limitsByPackageCode = [
            'trial' => 0,
            'starter' => 0,
            'growth' => 0,
            'business' => 1,
            'enterprise' => 1,
            'ultimate' => null,
            'unlimited' => null,
        ];

        $packages = DB::table('packages')
            ->select('uuid', 'code')
            ->get();

        $now = now();

        foreach ($packages as $package) {
            $limit = array_key_exists((string) $package->code, $limitsByPackageCode)
                ? $limitsByPackageCode[(string) $package->code]
                : 0;

            foreach ($featureNames as $featureCode => $featureName) {
                DB::table('package_features')->updateOrInsert(
                    [
                        'package_uuid' => (string) $package->uuid,
                        'feature_code' => $featureCode,
                    ],
                    [
                        'feature_name' => $featureName,
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
        if (! Schema::hasTable('package_features')) {
            return;
        }

        DB::table('package_features')
            ->whereIn('feature_code', ['allowance_governance', 'bpjs_governance', 'spt_masa_pph21'])
            ->delete();
    }
};
