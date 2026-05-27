<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Register `termination` as a standalone, sellable package feature.
 *
 * Packages included  : unlimited, business, enterprise, ultimate, umkm
 * Packages excluded  : starter, growth, trial  (basic tiers; sell separately)
 *
 * Simultaneously, the API routes now gate on `hcm.api.feature:termination`
 * instead of the generic `employee_lifecycle` code, so this migration must
 * run before any code that enforces the new gate in production.
 */
return new class extends Migration
{
    /** @var array<string> Package codes that should receive the `termination` feature */
    private const ELIGIBLE_PACKAGE_CODES = [
        'unlimited',   // Global-admin/internal — always gets every feature
        'business',
        'enterprise',
        'ultimate',
        'umkm',        // UMKM package already has promotion + resignation lifecycle features
    ];

    public function up(): void
    {
        if (! Schema::hasTable('packages') || ! Schema::hasTable('package_features')) {
            return;
        }

        $packages = DB::table('packages')
            ->whereIn('code', self::ELIGIBLE_PACKAGE_CODES)
            ->select('uuid', 'code')
            ->get();

        foreach ($packages as $package) {
            $exists = DB::table('package_features')
                ->where('package_uuid', (string) $package->uuid)
                ->where('feature_code', 'termination')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('package_features')->insert([
                'package_uuid'  => (string) $package->uuid,
                'feature_code'  => 'termination',
                'feature_name'  => 'Termination Management',
                'limit'         => null,   // unlimited — no per-company row cap
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('package_features')) {
            return;
        }

        $packageUuids = DB::table('packages')
            ->whereIn('code', self::ELIGIBLE_PACKAGE_CODES)
            ->pluck('uuid');

        DB::table('package_features')
            ->whereIn('package_uuid', $packageUuids)
            ->where('feature_code', 'termination')
            ->delete();
    }
};
