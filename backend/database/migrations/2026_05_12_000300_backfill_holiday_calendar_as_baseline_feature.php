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

        // Ensure all existing holiday_calendar rows are treated as included baseline.
        DB::table('package_features')
            ->where('feature_code', 'holiday_calendar')
            ->where('limit', 0)
            ->update([
                'limit' => 1,
                'feature_name' => 'Holiday Calendar',
                'updated_at' => now(),
            ]);

        $packages = DB::table('packages')
            ->select('uuid')
            ->get();

        foreach ($packages as $package) {
            $exists = DB::table('package_features')
                ->where('package_uuid', (string) $package->uuid)
                ->where('feature_code', 'holiday_calendar')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('package_features')->insert([
                'package_uuid' => (string) $package->uuid,
                'feature_code' => 'holiday_calendar',
                'feature_name' => 'Holiday Calendar',
                'limit' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('packages') || ! Schema::hasTable('package_features')) {
            return;
        }

        $legacyBaselineCodes = ['trial', 'starter', 'growth'];

        $legacyPackageUuids = DB::table('packages')
            ->whereIn('code', $legacyBaselineCodes)
            ->pluck('uuid')
            ->filter()
            ->values()
            ->all();

        if ($legacyPackageUuids === []) {
            return;
        }

        DB::table('package_features')
            ->where('feature_code', 'holiday_calendar')
            ->whereIn('package_uuid', $legacyPackageUuids)
            ->update([
                'limit' => 0,
                'updated_at' => now(),
            ]);
    }
};
