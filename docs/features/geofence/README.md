# Geofence Settings

## Ringkasan

Geofence membatasi lokasi absensi (punch in/out) karyawan dalam area geografis melingkar. Admin membuat lingkaran geofence — jika aktif, punch ditolak dengan `GEOFENCE_VIOLATION` bila di luar semua fence.

## Aktor & Role

| Role | Akses |
|------|-------|
| HCM Admin | CRUD geofence via `/geofences` web + API |
| Karyawan | Pasif — geofence mempengaruhi punch, tidak ada UI kelola |

## Halaman Aktif

| Path | Middleware | Route Name |
|------|-----------|------------|
| GET `/geofences` | `hcm.web.admin` | `geofences` |
| GET `/attendance-employee` | `hcm.web.feature:attendance`, `hcm.web.employee:attendance-admin` | `attendance-employee` |

## Flow End-to-End

1. **Admin** buka `/geofences` → lihat daftar + Add/Edit modal dengan Leaflet map
2. **Admin** set titik pusat (click/drag marker) + radius + nama + status aktif
3. **Karyawan** buka `/attendance-employee` → peta Leaflet overlay geofence + jarak real-time dari posisi GPS
4. **Karyawan** punch → API cek `findActiveGeofences()` + `isWithinAnyGeofence()`
   - Dalam area → punch proceed
   - Luar semua geofence aktif → 422 `GEOFENCE_VIOLATION`
   - Tidak ada geofence aktif → punch proceed (skip pengecekan)

## Decision Tree Punch

```
Ada geofence aktif?
  ├─ Tidak → punch OK
  └─ Ya → posisi dalam salah satu fence?
       ├─ Ya → punch OK
       └─ Tidak → 422 GEOFENCE_VIOLATION
```

## Integrasi

- **Attendance punch** (`AttendanceEmployeeController::punch`): inject `GeofenceService`
- **Real-time UI** (`attendance-punch-map.js`): render geofence + haversine distance di browser
- **CRUD UI** (`geofence-management-data.js`): Leaflet map picker + form validation via `ArcavValidation`

## Edge Case & Status

| Status / Edge Case | Perilaku |
|---|---|
| No active geofence | Punch bypass geofence check entirely |
| Multiple active geofences | Check ALL — dalam salah satu → OK |
| Inactive geofence | Ignored (tidak cek) |
| UI vs BE mismatch | UI render dari DB via route (bukan hardcode) |
| Duplicate name (sama company) | 422 unique violation (DB constraint + app validation) |
