<?php

namespace App\Services;

use App\Models\Geofence;
use Illuminate\Database\Eloquent\Collection;

class GeofenceService
{
    public function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function findActiveGeofences(int $companyId): Collection
    {
        return Geofence::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get();
    }

    public function isWithinAnyGeofence(float $latitude, float $longitude, int $companyId): ?Geofence
    {
        $geofences = $this->findActiveGeofences($companyId);

        foreach ($geofences as $geofence) {
            $distance = $this->haversineDistance(
                $latitude,
                $longitude,
                (float) $geofence->latitude,
                (float) $geofence->longitude
            );

            if ($distance <= (int) $geofence->radius_meters) {
                return $geofence;
            }
        }

        return null;
    }
}
