<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LocationService
{
    /**
     * Reverse geocode coordinates to get location name and address
     * Uses Nominatim (OpenStreetMap) API - free & no authentication needed
     * Results are cached for 30 days to minimize API calls
     *
     * @param float $latitude
     * @param float $longitude
     * @return array{name: string, address: string, source: string}
     */
    public static function reverseGeocode(float $latitude, float $longitude): array
    {
        // Cache key based on rounded coordinates to reduce cache entries
        $roundedLat = round($latitude, 4); // ~11 meters precision
        $roundedLng = round($longitude, 4);
        $cacheKey = "geolocation_{$roundedLat}_{$roundedLng}";

        // Check cache first (30 days)
        $cached = Cache::remember($cacheKey, 60 * 60 * 24 * 30, function () use ($latitude, $longitude) {
            return self::queryNominatim($latitude, $longitude);
        });

        return $cached;
    }

    /**
     * Query Nominatim API to reverse geocode coordinates
     */
    private static function queryNominatim(float $latitude, float $longitude): array
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'SmartHR-Attendance/1.0'])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'json',
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'zoom' => 18,
                    'addressdetails' => 1,
                ]);

            if ($response->successful()) {
                return self::parseNominatimResponse($response->json());
            }

            return self::defaultResponse('Unknown Location', 'GPS: '.$latitude.', '.$longitude);
        } catch (\Exception $e) {
            \Log::warning('Nominatim API error: '.$e->getMessage());

            return self::defaultResponse('Unknown Location', 'GPS: '.$latitude.', '.$longitude);
        }
    }

    /**
     * Parse Nominatim response and extract location name and address
     */
    private static function parseNominatimResponse(array $data): array
    {
        $address = $data['address'] ?? [];
        
        // Try to build a hierarchical location name: Building/Street > Village/Subdistrict > District > City
        $locationName = self::buildLocationName($address);
        $locationAddress = $data['display_name'] ?? '';

        // Limit address to first 255 characters (database field limit)
        if (strlen($locationAddress) > 255) {
            $locationAddress = substr($locationAddress, 0, 252).'...';
        }

        return self::defaultResponse($locationName, $locationAddress);
    }

    /**
     * Build readable location name from address components
     */
    private static function buildLocationName(array $address): string
    {
        // Indonesian address hierarchy
        $hierarchyPriority = [
            'building',        // <-- Check for buildings first
            'restaurant',
            'cafe',
            'shop',
            'office',
            'public_building',
            'amenity',
            'village',         // Kelurahan
            'suburb',
            'town',            // Kota
            'municipality',    // Kabupaten/Kota
            'county',
            'state_district',
            'state',
        ];

        // Try to find the most relevant location component
        foreach ($hierarchyPriority as $component) {
            if (! empty($address[$component])) {
                $primaryLocation = $address[$component];
                
                // Build secondary location (parent administrative area)
                $secondaryLocation = null;
                if (in_array($component, ['village', 'suburb', 'town'])) {
                    // If we have village/suburb/town, try to add municipality/district
                    $secondaryLocation = $address['municipality'] ?? $address['county'] ?? null;
                } elseif (!empty($address['municipality'])) {
                    $secondaryLocation = $address['municipality'];
                }

                if ($secondaryLocation && $secondaryLocation !== $primaryLocation) {
                    return "{$primaryLocation}, {$secondaryLocation}";
                }

                return $primaryLocation;
            }
        }

        // Fallback: use city or state or country
        return $address['city'] ?? $address['county'] ?? $address['country'] ?? 'Unknown Location';
    }

    /**
     * Return default response format
     */
    private static function defaultResponse(string $name, string $address): array
    {
        return [
            'name' => $name,
            'address' => $address,
            'source' => 'gps',
        ];
    }

    /**
     * Create manual location entry from provided name and address
     */
    public static function createManualLocation(string $name, string $address = ''): array
    {
        return [
            'name' => $name,
            'address' => $address,
            'source' => 'manual',
        ];
    }
}
