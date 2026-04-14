<?php

namespace Tests\Unit;

use App\Services\LocationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LocationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Test reverse geocoding with Nominatim API
     */
    public function test_reverse_geocode_returns_location_name(): void
    {
        // Mock API response for Jakarta coordinates
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'display_name' => 'Jl. Gatot Subroto, Jakarta Pusat, DKI Jakarta, Indonesia',
                'address' => [
                    'building' => 'PT. Company Building',
                    'village' => 'Senayan',
                    'municipality' => 'Central Jakarta',
                    'state' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
            ]),
        ]);

        $result = LocationService::reverseGeocode(-6.2088, 106.8456);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('address', $result);
        $this->assertArrayHasKey('source', $result);
        $this->assertEquals('PT. Company Building, Central Jakarta', $result['name']);
        $this->assertEquals('gps', $result['source']);
    }

    /**
     * Test caching prevents duplicate API calls
     */
    public function test_reverse_geocode_uses_cache(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'display_name' => 'Jakarta, Indonesia',
                'address' => ['country' => 'Indonesia'],
            ]),
        ]);

        // First call
        LocationService::reverseGeocode(-6.2088, 106.8456);

        // Second call should use cache - verify cache hit
        $cached = LocationService::reverseGeocode(-6.2088, 106.8456);
        
        // Both should return same result
        $first = LocationService::reverseGeocode(-6.2089, 106.8457);
        $second = LocationService::reverseGeocode(-6.2089, 106.8457);
        
        $this->assertEquals($first['name'], $second['name']);
        $this->assertEquals('gps', $cached['source']);
    }

    /**
     * Test fallback when API fails
     */
    public function test_reverse_geocode_fallback_on_api_error(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 500),
        ]);

        $result = LocationService::reverseGeocode(-6.2088, 106.8456);

        $this->assertEquals('Unknown Location', $result['name']);
        $this->assertStringContainsString('GPS: -6.2088', $result['address']);
        $this->assertEquals('gps', $result['source']);
    }

    /**
     * Test manual location creation
     */
    public function test_create_manual_location(): void
    {
        $result = LocationService::createManualLocation('Head Office', 'Jl. Sudirman No. 1, Jakarta');

        $this->assertEquals('Head Office', $result['name']);
        $this->assertEquals('Jl. Sudirman No. 1, Jakarta', $result['address']);
        $this->assertEquals('manual', $result['source']);
    }

    /**
     * Test location hierarchy building
     */
    public function test_location_name_hierarchy(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'display_name' => 'Complete Address Here',
                'address' => [
                    'restaurant' => 'Restaurant Name',
                    'village' => 'Senayan',
                    'municipality' => 'Central Jakarta',
                    'state' => 'DKI Jakarta',
                    'country' => 'Indonesia',
                ],
            ]),
        ]);

        $result = LocationService::reverseGeocode(-6.2088, 106.8456);

        // Should prioritize restaurant over village since it's first in hierarchy
        $this->assertStringContainsString('Restaurant Name', $result['name']);
    }
}
