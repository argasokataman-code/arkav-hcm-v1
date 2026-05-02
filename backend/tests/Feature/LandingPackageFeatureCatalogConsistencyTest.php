<?php

namespace Tests\Feature;

use App\Models\Package;
use Database\Seeders\LandingPackagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPackageFeatureCatalogConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_packages_seed_required_feature_codes_for_menu_gates(): void
    {
        $this->seed(LandingPackagesSeeder::class);

        $requiredFeatureCodes = [
            'employee_management',
            'attendance',
            'leave_management',
            'holiday_calendar',
            'employee_lifecycle',
            'payroll',
            'performance',
            'training',
            'goal_tracking',
            'asset_management',
            'tickets',
            'employee_document_center',
        ];

        foreach (['trial', 'starter', 'growth', 'business', 'enterprise'] as $packageCode) {
            $package = Package::query()->where('code', $packageCode)->firstOrFail();
            $seededFeatureCodes = $package->features()->pluck('feature_code')->all();

            foreach ($requiredFeatureCodes as $featureCode) {
                $this->assertContains(
                    $featureCode,
                    $seededFeatureCodes,
                    sprintf('Package %s is missing required seeded feature code %s.', $packageCode, $featureCode)
                );
            }
        }
    }
}
