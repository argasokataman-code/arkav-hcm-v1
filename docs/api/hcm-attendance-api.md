# Attendance API (Phase 1)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/AttendanceController.php`.

## Base path

`/v1/hcm`

Tenant context:
- Endpoint attendance membaca `activeCompany` dari middleware tenant context.
- Header opsional untuk override company aktif: `X-Company-Id` atau `X-Company-Code`.
- Jika company yang dipilih bukan membership aktif user, API mengembalikan `403 TENANT_FORBIDDEN`.
- Path parameter attendance/schedule pada runtime aktif tetap `numeric-only` (legacy integer id), termasuk `{id}` pada selfie download admin dan `{userId}` pada schedule timing.
- `PUT /attendance/admin/record` — lookup record + create menggunakan `company_id` aktif; admin dari company lain tidak dapat menulis record employee company lain. Jika `userId` bukan member company aktif, API mengembalikan `404 USER_NOT_IN_COMPANY`.
- `GET /timesheets` — query `attendance_records` di-scope ke `company_id` aktif.

## Attendance (admin)

### GET `/attendance/admin`

RBAC:
- HCM Admin only

Query:
- `date` optional (date) — default hari ini (timezone app)
- `search` optional string max 100
- `page` optional int ≥1, `perPage` optional int 1..100 (default 50)
- `department` optional string max 100 — filter `employee_profiles.team` (exact)
- `status` optional `present|absent|needs_review`
- `sort` optional `name_asc|name_desc|checkin_asc|checkin_desc|production_desc|production_asc`

Success `200`:
- `meta.departments[]` — daftar team unik (master) untuk dropdown filter
- `meta.summary` — agregat untuk **seluruh** user yang cocok filter (bukan hanya halaman): `totalEmployees`, `present`, `absent`, `lateLogin`, `uninformed`, `permission`
- `meta.pagination`: `page`, `perPage`, `total`, `totalPages`
- `data[]`: row per user pada halaman ini (checkIn/checkOut/break/late/overtime/production/correction)

### PUT `/attendance/admin/record`

RBAC:
- HCM Admin only

Body:
- `userId` required integer exists `users.id`
- `workDate` required date
- `checkInTime` optional `H:i`
- `checkOutTime` optional `H:i`
- `breakMinutes` optional int 0..720
- `lateMinutes` optional int 0..1440 (requires `checkInTime`)

Validasi tambahan:
- jika `checkInTime` dan `checkOutTime` diisi: `checkOutTime >= checkInTime` (422 `VALIDATION_ERROR`)
- jika tanpa `checkInTime` tapi `lateMinutes > 0` → 422
- jika semua kosong (`checkInTime`, `checkOutTime`, `breakMinutes=0`, `lateMinutes=0`) dan record ada → record dihapus (`data.deleted=true`)
- jika `userId` tidak termasuk membership aktif pada tenant yang sedang dipilih → 404 `USER_NOT_IN_COMPANY`

Success `200`:

```json
{ "success": true, "data": { "recordId": 123 } }
```

## Attendance (employee/self)

### GET `/attendance/me/today`

RBAC:
- Authenticated: self only

Success `200`:
- Mengembalikan summary UI (progress, punch state, badge) + lokasi punch in/out jika ada
- Menyertakan `profilePhotoUrl` (nullable) dari `employee_profiles.profile_photo_path` untuk avatar halaman `/attendance-employee`

### GET `/attendance/me/history`

Query:
- `days` optional int 1..90 (default 30)

Success `200`:
- `data[]` berisi riwayat per tanggal (label, status badge, production label, overtime)

### GET `/attendance/me/stats`

Success `200`:
- `todayHours`, `weekHours`, `monthHours`, `monthOvertimeHours` + target UI
- Baseline saat ini: target harian `8` jam, week target berbasis hari kerja (Senin-Jumat) dalam 1 minggu penuh (`40` jam), month target berbasis jumlah hari kerja (Senin-Jumat) pada bulan berjalan, dan overtime dihitung setelah melewati `8` jam kerja net per hari.

### POST `/attendance/me/punch`

Body:
- `latitude` required numeric between -90..90
- `longitude` required numeric between -180..180

Behavior:
- jika belum punch in → set check-in, response `data.action="in"`
- jika sudah check-in dan belum check-out:
  - jika break sedang berjalan → 422 `BREAK_IN_PROGRESS`
  - set check-out, bisa auto `needs_review` jika punch out terlalu cepat, response `data.action="out"`
- jika check-in dan check-out sudah ada → 422 `ATTENDANCE_ALREADY_COMPLETE`

### POST `/attendance/me/break`

Behavior:
- jika belum punch-in → 422 `ATTENDANCE_NOT_STARTED`
- jika sudah punch-out → 422 `ATTENDANCE_ALREADY_COMPLETE`
- toggle start/end break, update `breakMinutes`

### POST `/attendance/me/correction-request`

Body:
- `workDate` required date
- `reason` required string min 5 max 500

Errors:
- `404 ATTENDANCE_NOT_FOUND`
- `422 ATTENDANCE_NOT_COMPLETE`

Success `200`:

```json
{ "success": true, "data": { "correctionStatus": "requested" } }
```

### POST `/attendance/me/selfie`

RBAC:
- Authenticated: self only

Body:
- `selfie_base64` required string base64 image
- `timestamp` optional integer

Errors:
- `422 ATTENDANCE_NOT_STARTED` jika employee belum punya attendance record hari ini
- `422 VALIDATION_ERROR` jika payload base64 tidak valid

Success `200`:

```json
{
  "success": true,
  "data": {
    "attendance_id": 123,
    "selfie_path": "selfie/1/45_2026-04-20_1770000000.jpg",
    "uploaded_at": "2026-04-20T08:00:00.000000Z"
  }
}
```

### GET `/attendance/me/selfie/status`

RBAC:
- Authenticated: self only

Success `200`:
- `data.has_selfie` boolean
- `data.selfie` nullable object dengan `path`, `uploaded_at`, `is_encrypted`

### GET `/attendance/admin/records/{id}/selfie/download`

RBAC:
- HCM Admin only

Path:
- `{id}` adalah numeric attendance record id pada tenant aktif

Behavior:
- Wajib punya active company context
- Attendance record di-resolve di dalam tenant aktif; admin tidak bisa mengunduh selfie dari tenant lain
- Jika file selfie belum ada, API mengembalikan `404 SELFIE_NOT_FOUND`

## Timesheets (admin)

### GET `/timesheets`

RBAC:
- HCM Admin only

Query:
- `dateFrom` optional date
- `dateTo` optional date, must be `>= dateFrom` when both diisi
- `project` optional string max 100 (filter)
- `sort` optional enum `employee_asc|employee_desc|date_desc|date_asc|worked_desc|worked_asc`
- `page` optional int >=1
- `perPage` optional int 1..200 (default 50)

Success `200`:
- `meta.projects[]` distinct label proyek (team + ` Ops`) pada rentang tanggal
- `meta.pagination`: `page`, `perPage`, `total`, `totalPages` — paginasi **di database** (tanpa batas 2000 baris artifisial)

## Schedule timing (admin)

### GET `/schedule-timing`

RBAC:
- HCM Admin only

Query:
- `search` optional string max 100 (nama atau designation; filter SQL)
- `sort` optional enum `name_asc|name_desc|start_asc|start_desc` (urut `start_*` diterapkan per halaman hasil)
- `page` optional int >=1
- `perPage` optional int 1..100 (default 50)

### PUT `/schedule-timing/{userId}`

RBAC:
- HCM Admin only

Path:
- `{userId}` adalah numeric user id pada tenant aktif

Body:
- `shiftId` optional int exists `hcm_shifts.id` (must be active)
- `startTime` required_without:shiftId, `H:i`
- `endTime` required_without:shiftId, `H:i`

Validasi tambahan:
- `endTime > startTime` (422 `VALIDATION_ERROR`)
- Jika shift inactive/not found → 422 `VALIDATION_ERROR`
- Jika `{userId}` bukan membership aktif pada tenant yang dipilih → 404 `USER_NOT_IN_COMPANY`

### DELETE `/schedule-timing/{userId}`

RBAC:
- HCM Admin only

Path:
- `{userId}` adalah numeric user id pada tenant aktif

Errors:
- `404 USER_NOT_IN_COMPANY` jika target user bukan member company aktif

