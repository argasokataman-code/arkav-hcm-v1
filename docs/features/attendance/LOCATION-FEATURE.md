# Attendance Location Feature - API Documentation

## Overview
This document describes the Attendance Location feature that captures and displays human-readable location names instead of raw GPS coordinates.

## Features

### 1. Automatic Location Name Capture
- When employees punch in/out, GPS coordinates are automatically reverse-geocoded to get a readable location name
- Uses Nominatim (OpenStreetMap) API - free, no authentication required
- Results cached for 30 days to minimize API calls and latency

### 2. Location Display in Reports
- Attendance reports show location names (e.g., "Jakarta, Indonesia") instead of coordinates
- Hierarchical display: Building/Street > Village > District > City
- Both check-in and check-out locations tracked separately

### 3. Support for Manual Entry
- Admin can manually set location names via the upsert endpoint
- Supports both auto-detected (GPS) and manual location entry
- Location source tracked: 'gps', 'manual', or 'pending'

---

## Database Schema Changes

### New Fields in `attendance_records` table

```sql
-- Check-in location
check_in_location_name VARCHAR(255) -- Readable location name
check_in_location_address TEXT -- Full address from reverse geocoding
check_in_location_source ENUM('gps', 'manual', 'pending') DEFAULT 'gps'

-- Check-out location  
check_out_location_name VARCHAR(255)
check_out_location_address TEXT
check_out_location_source ENUM('gps', 'manual', 'pending') DEFAULT 'gps'
```

**Migration:** `2026_04_14_000030_add_location_names_to_attendance_records.php`

---

## API Endpoints

### 1. POST /v1/hcm/attendance/me/punch
Punch in/out with automatic location capture

**Request:**
```json
{
  "latitude": -6.2088,
  "longitude": 106.8456
}
```

**Response (Check-in):**
```json
{
  "success": true,
  "data": {
    "action": "in",
    "message": "Punch in recorded.",
    "location": "Jakarta, Central Jakarta"
  }
}
```

**Response (Check-out):**
```json
{
  "success": true,
  "data": {
    "action": "out",
    "needsReview": false,
    "message": "Punch out recorded.",
    "location": "Jakarta, East Jakarta"
  }
}
```

---

### 2. GET /v1/hcm/attendance/me/today
Get today's attendance with location names

**Response:**
```json
{
  "success": true,
  "data": {
    "userName": "John Doe",
    "punchInAtFormatted": "09:00 AM",
    "punchOutAtFormatted": "05:30 PM",
    
    // NEW: Location names
    "checkInLocationName": "Jakarta, Central Jakarta",
    "checkInLocationAddress": "Jl. Gatot Subroto, Jakarta Pusat, Indonesia",
    "checkOutLocationName": "Jakarta, East Jakarta",
    "checkOutLocationAddress": "Jl. H. R. Rasuna Said, Jakarta Selatan, Indonesia",
    
    // Old: Coordinates (kept for backward compatibility)
    "checkInLatitude": -6.2088,
    "checkInLongitude": 106.8456,
    "checkOutLatitude": -6.2089,
    "checkOutLongitude": 106.8457,
    
    // ... other fields
  }
}
```

---

### 3. GET /v1/hcm/attendance/me/history?days=30
Get attendance history with location names

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "dateLabel": "14 Apr 2026",
      "checkIn": "09:00 AM",
      "checkOut": "05:30 PM",
      "checkInLocation": "Jakarta, Central Jakarta",
      "checkOutLocation": "Jakarta, East Jakarta",
      "statusLabel": "Present",
      "break": "30 Min",
      // ... other fields
    },
    // ... more records
  ]
}
```

---

### 4. GET /v1/hcm/attendance/admin
Admin view attendance with location names

**Response includes:**
```json
{
  "success": true,
  "data": [
    {
      "employeeName": "John Doe",
      "team": "Engineering",
      "checkIn": "09:00 AM",
      "checkOut": "05:30 PM",
      "checkInLocation": "Jakarta, Central Jakarta",
      "checkOutLocation": "Jakarta, East Jakarta",
      "statusLabel": "Present",
      // ... other fields
    },
    // ... more employees
  ]
}
```

---

## Location Service (`App\Services\LocationService`)

### Key Methods

#### `reverseGeocode(float $latitude, float $longitude): array`
Reverse geocodes GPS coordinates to get location name and address

**Returns:**
```php
[
    'name' => 'Jakarta, Central Jakarta',
    'address' => 'Jl. Gatot Subroto, Jakarta Pusat, Indonesia',
    'source' => 'gps'
]
```

**Caching:** Results cached for 30 days using rounded coordinates (11m precision)

---

#### `createManualLocation(string $name, string $address = ''): array`
Create manual location entry for admin-set locations

**Returns:**
```php
[
    'name' => 'Head Office',
    'address' => 'Jl. Sudirman, Jakarta',
    'source' => 'manual'
]
```

---

## Implementation Details

### Reverse Geocoding Service
- **Provider:** Nominatim (OpenStreetMap API)
- **Endpoint:** https://nominatim.openstreetmap.org/reverse
- **Timeout:** 5 seconds
- **Error Handling:** Falls back to GPS coordinates if API fails
- **Caching:** Laravel Cache with 30-day TTL

### Location Name Hierarchy
Priority order for extracting readable location names:

1. Building/Structure (building, restaurant, cafe, office, etc.)
2. Village/Suburb (village, suburb, town)
3. Municipality (municipality, county)
4. State/Region
5. Country fallback

Example: "Jakarta, Central Jakarta" = village/township + municipality

---

## Model Updates

### AttendanceRecord Model
```php
protected $fillable = [
    // ... existing fields
    'check_in_location_name',
    'check_in_location_address',
    'check_in_location_source',
    'check_out_location_name',
    'check_out_location_address',
    'check_out_location_source',
];

protected function casts(): array
{
    return [
        'check_in_location_source' => 'string',
        'check_out_location_source' => 'string',
        // ... other casts
    ];
}
```

---

## Report Views

### Updated Blade Templates
- `resources/views/attendance-report.blade.php` - Shows location names
- `resources/views/attendance-admin.blade.php` - Shows location names
- `resources/views/attendance-employee.blade.php` - Shows location names

### Display Format
```
Check-in:  09:00 AM at Jakarta, Central Jakarta
Check-out: 05:30 PM at Jakarta, East Jakarta
```

---

## Testing

### Test Cases

1. **GPS Auto-Detection**
   - Punch with valid GPS coordinates
   - Verify location name is fetched and stored
   - Verify location appears in API responses

2. **Caching**
   - Punch at same location twice
   - Verify second punch uses cached location
   - Verify no duplicate API calls

3. **Admin Manual Entry**
   - Admin sets manual location via upsert endpoint
   - Verify location source = 'manual'
   - Verify location appears in reports

4. **Error Handling**
   - Punch with invalid coordinates
   - Verify fallback to GPS display
   - Verify no API errors in logs

5. **Report Display**
   - Generate attendance report
   - Verify location names shown (not coordinates)
   - Verify both check-in and check-out locations visible

---

## Configuration

### Environment Variables
No additional configuration needed. Uses Nominatim public API.

### Caching
Location caching uses Laravel's configured cache (default: file cache)

### API Rate Limits
- Nominatim public API: ~1 request/second recommended
- Our implementation throttles via caching to prevent rate limiting

---

## Migration Guide

### For Existing Deployments

1. Run migration:
   ```bash
   php artisan migrate
   ```

2. Optional: Backfill location names for existing records
   ```bash
   php artisan attendance:backfill-locations
   ```

3. Update frontend to display location fields from API responses

---

## Troubleshooting

### Locations showing as "GPS: -6.2088, 106.8456"
- Nominatim API may be down or slow
- Check application logs for API errors
- Verify internet connectivity
- Fallback is automatic; users can still see coordinates

### Slow punch-in/out response
- First punch at new location: ~1 second (API call)
- Subsequent punches at same location: <100ms (cached)
- Consider using Nominatim or Mapbox API in production with API keys

### Foreign language location names
- Nominatim respects local language settings
- May return Indonesian/regional names (e.g., "Kelurahan", "Kabupaten")
- This is expected behavior in Indonesia

---

## Future Enhancements

1. **Geofencing:** Verify punch location is within company office radius
2. **Location History:** Track employee location throughout day
3. **Map View:** Visualize all punch locations on map
4. **Offline Support:** Pre-cache common location names for offline mode
5. **Custom Location Names:** Admin can suggest/override location names

