# Geofence — Implementation

## Arsitektur

```
Route (web.php) ──→ HcmGeofenceController ──→ Geofence model
                        │                           │
                        ↓                           ↓
                   GeofenceService            geofences table
                        │
                        ↓
              AttendanceEmployeeController::punch()
```

## Database

**Table `geofences`**

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned | PK, auto-increment |
| uuid | char(36) | UNIQUE, generated via `AssignsUuid` trait |
| company_id | bigint unsigned | INDEX, tenant scope |
| name | varchar(100) | UNIQUE per company (DB constraint + app validation) |
| latitude | decimal(10,7) | Range: -90 to 90 |
| longitude | decimal(10,7) | Range: -180 to 180 |
| radius_meters | unsigned int | Default 200, min 10, max 50000 |
| is_active | tinyint(1) | Default true |

**Migration:** `2026_06_24_000003_create_geofences_table.php`  
**Constraint:** `2026_06_24_000004_add_constraints_to_geofences_table.php` (unique `company_id`, `name`)

## API Contract

Prefix: `/v1/hcm/geofences`

### RBAC

| Action | Permission |
|--------|-----------|
| Index, Show | `attendance.manage` OR `attendance.view` |
| Store, Update, Destroy | `attendance.manage` |

### Endpoints

| Method | URI | Controller | Response |
|--------|-----|-----------|----------|
| GET | `/geofences` | `index` | 200 `{success, data[], meta{page,perPage,total}}` |
| POST | `/geofences` | `store` | 201 `{success, data{}}` |
| GET | `/geofences/{id or uuid}` | `show` | 200 `{success, data{}}` |
| PUT | `/geofences/{id or uuid}` | `update` | 200 `{success, data{}}` |
| DELETE | `/geofences/{id or uuid}` | `destroy` | 200 `{success}` |

### Error Codes

| Code | Status | Condition |
|------|--------|-----------|
| `AUTH_FORBIDDEN` | 403 | Role tanpa permission |
| `NOT_FOUND` | 404 | Cross-company atau ID tidak ada |
| `TENANT_CONTEXT_REQUIRED` | 422 | Missing active company |
| `GEOFENCE_VIOLATION` | 422 | Punch di luar semua geofence aktif |

### Serialized Shape

```json
{
  "id": 1,
  "uuid": "abc-...",
  "company_id": 1,
  "name": "Kantor Pusat",
  "latitude": -6.2088,
  "longitude": 106.8456,
  "radius_meters": 500,
  "is_active": true,
  "created_at": "2026-06-24T00:00:00+00:00",
  "updated_at": "2026-06-24T00:00:00+00:00"
}
```

## Key Implementation Details

### GeofenceService

- `findActiveGeofences(companyId)` — returns all active geofences for company
- `isWithinAnyGeofence(lat, lng, companyId)` — iterates all active, returns first matching or null
- `haversineDistance(lat1, lng1, lat2, lng2)` — standard haversine formula in meters

### Attendance Punch Integration

File: `AttendanceEmployeeController::punch()` (line 387-397)

```php
$geofenceService = new GeofenceService;
$activeGeofences = $geofenceService->findActiveGeofences($activeCompanyId);
if ($activeGeofences->isNotEmpty() && ! $geofenceService->isWithinAnyGeofence($lat, $lng, $activeCompanyId)) {
    return response()->json([...], 422); // GEOFENCE_VIOLATION
}
```

### View Rendering

File: `routes/web.php:819` — route closure queries first active geofence, passes `$defaultGeofence` to view. Blade uses dynamic `data-gf-*` attributes.

## Test Coverage

### HcmGeofenceApiTest (18 tests)

| Test | What it covers |
|------|---------------|
| `test_list_geofences_empty` | Empty list |
| `test_create_geofence` | Happy path |
| `test_show_geofence` | Single by ID |
| `test_update_geofence` | Partial update |
| `test_delete_geofence` | Deletion |
| `test_create_geofence_rejects_duplicate_name` | 422 duplicate (same company) |
| `test_create_geofence_rejects_invalid_radius` | 422 radius < 10 |
| `test_create_geofence_rejects_invalid_coordinates` | 422 lat > 90 |
| `test_non_admin_cannot_access_geofences` | 403 member role |
| `test_cross_company_geofence_invisible` | 404 other company |
| `test_geofence_not_found_returns_404` | 404 non-existent |
| `test_geofence_list_returns_paginated` | Paginated list |
| `test_create_geofence_defaults_is_active` | Default true |
| `test_update_geofence_rejects_duplicate_name` | 422 rename to existing |
| `test_list_geofences_search_filter` | Search by name |
| `test_list_geofences_per_page_clamping` | perPage capped at 100 |
| `test_show_geofence_by_uuid` | UUID route binding |
| `test_create_geofence_at_pole` | lat=90/-90 |

### AttendanceApiTest (geofence section — 6 tests)

| Test | What it covers |
|------|---------------|
| `test_attendance_punch_within_geofence_succeeds` | Punch inside |
| `test_attendance_punch_outside_geofence_rejected` | Punch outside → 422 |
| `test_attendance_punch_works_without_geofence` | No geofence → punch OK |
| `test_attendance_punch_with_inactive_geofence_ignored` | Inactive ignored |
| `test_attendance_punch_at_geofence_boundary_inside_succeeds` | Edge just inside |
| `test_attendance_punch_at_geofence_boundary_outside_rejected` | Edge just outside |

### EmployeeScopedWebRoutesTest (1 test)

| Test | What it covers |
|------|---------------|
| `test_attendance_employee_page_renders_geofence_from_database` | Dynamic geofence data in HTML (not hardcoded) |

## Known Gaps

1. **FK `company_id`** — Tidak ada FK constraint karena `companies` PK adalah `uuid`, bukan `id`. MySQL 9.x requires UNIQUE key on referenced column. Data integrity dijaga via application logic.
