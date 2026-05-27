<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\FeatureClassification;
use App\Services\PackageFeatureCatalogRuntimeService;

class PackageFeatureCatalogRuntimeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_overrides_change_mvp_and_addon()
    {
        // Mark tickets as addon via DB override (updateOrCreate — backfill migration may pre-populate)
        FeatureClassification::updateOrCreate(['feature_code' => 'tickets'], ['tier' => 'addon']);

        $service = new PackageFeatureCatalogRuntimeService();
        $built = $service->build();

        $this->assertIsArray($built);
        $this->assertArrayHasKey('mvp_feature_codes', $built);
        $this->assertArrayHasKey('addon_feature_codes', $built);

        $this->assertNotContains('tickets', $built['mvp_feature_codes']);
        $this->assertContains('tickets', $built['addon_feature_codes']);
    }
}
