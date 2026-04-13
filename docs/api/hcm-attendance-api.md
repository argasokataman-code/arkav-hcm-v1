# Attendance API (Phase 1)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/AttendanceController.php`.

## Base path

`/v1/hcm`

Tenant context:
- Endpoint attendance membaca `activeCompany` dari middleware tenant context.
- Header opsional untuk override company aktif: `X-Company-Id` atau `X-Company-Code`.
- Jika company yang dipilih bukan membership aktif user, API mengembalikan `403 TENANT_FORBIDDEN`.
- `PUT /attendance/admin/record` — lookup record + create menggunakan `company_id` aktif; admin dari company lain tidak dapat menulis record employee company lain.
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

### GET `/attendance/me/history`

Query:
- `days` optional int 1..90 (default 30)

Success `200`:
- `data[]` berisi riwayat per tanggal (label, status badge, production label, overtime)

### GET `/attendance/me/stats`

Success `200`:
- `todayHours`, `weekHours`, `monthHours`, `monthOvertimeHours` + target UI

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

## Timesheets (admin)

### GET `/timesheets`

RBAC:
- HCM Admin only

Query:
- `dateFrom`/`dateTo` optional date
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

Body:
- `shiftId` optional int exists `hcm_shifts.id` (must be active)
- `startTime` required_without:shiftId, `H:i`
- `endTime` required_without:shiftId, `H:i`

Validasi tambahan:
- `endTime > startTime` (422 `VALIDATION_ERROR`)
- Jika shift inactive/not found → 422 `VALIDATION_ERROR`

### DELETE `/schedule-timing/{userId}`

RBAC:
- HCM Admin only

