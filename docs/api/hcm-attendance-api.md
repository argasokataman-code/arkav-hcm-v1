# Attendance API (Phase 1)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/AttendanceController.php`.

## Base path

`/v1/hcm`

Tenant context:
- Endpoint attendance membaca `activeCompany` dari middleware tenant context.
- Header opsional untuk override company aktif: `X-Company-Id` atau `X-Company-Code`.
- Jika company yang dipilih bukan membership aktif user, API mengembalikan `403 TENANT_FORBIDDEN`.
- **Global Super Admin bypass:** user dengan `users.is_super_admin = 1` melewati scope `company_id` pada attendance queries (lintas tenant).
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

### GET `/attendance/admin/export`

RBAC:
- HCM Admin only

Query:
- `format` optional `xlsx|csv` (default `xlsx`)
- `source` optional `live|archive` (default `live`)
- `date` optional (date)
- `search` optional string max 100
- `department` optional string max 100
- `status` optional `present|absent|needs_review`
- `sort` optional `name_asc|name_desc|checkin_asc|checkin_desc|production_desc|production_asc`
- `snapshotId` required integer when `source=archive`

Behavior:
- `source=live` mengekspor hasil attendance admin dengan filter/sort yang sama seperti endpoint list.
- `source=archive` mengekspor snapshot attendance tenant dari Reports Hub.
- Archive export menolak snapshot yang bukan tipe `attendance` atau belum status `completed`.

Success:
- `200` stream file sesuai `format` (`.xlsx` atau `.csv`).

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
- `422 ATTENDANCE_NOT_STARTED` jika employee belum punya attendance record hari ini atau `check_in_at` belum terisi
- `422 VALIDATION_ERROR` jika payload base64 tidak valid, bukan image yang didukung (`jpeg/png/webp`), atau ukuran file melebihi batas 5MB (decoded)

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
- `department` optional string max 100 (nama departemen)

### GET `/schedule-timing/export`

RBAC:
- HCM Admin only

Tujuan:
- Export daftar shift & schedule dalam format `xlsx` atau `csv` dari backend stream response.

Query:
- `format` optional enum `xlsx|csv` (default `xlsx`)
- `search` optional string max 100
- `sort` optional enum `name_asc|name_desc|start_asc|start_desc`
- `department` optional string max 100

Behavior:
- Tetap tenant-scoped mengikuti company aktif.
- Kolom export: `Name`, `Department`, `Job Title`, `Available Timings`, `Shift`, `Source`.

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
- `endTime != startTime` (422 `VALIDATION_ERROR`); overnight (`endTime < startTime`) diperbolehkan
- Jika `shiftId` dikirim, shift harus aktif dan berada dalam tenant scope
- Jika `{userId}` bukan membership aktif pada tenant yang dipilih → 404 `USER_NOT_IN_COMPANY`

Success `200`:

```json
{ "success": true, "data": { "id": 123 } }
```

### DELETE `/schedule-timing/{userId}`

RBAC:
- HCM Admin only

Path:
- `{userId}` adalah numeric user id pada tenant aktif

Errors:
- `404 USER_NOT_IN_COMPANY` jika target user bukan member company aktif

## Smart attendance & shifting (admin)

### POST `/smart-attendance-shifting/generate`

RBAC:
- HCM Admin only

Tujuan:
- Generate weekly schedule otomatis berbasis data tenant (employee membership, shift template aktif, histori attendance),
- Validasi pelanggaran rule jadwal,
- Analisis attendance (`late`, `early_leave`, `absent`, `overtime`),
- Beri rekomendasi fairness/fatigue yang explainable.

Body (optional fields):
- `weekStart` date
- `employeeIds` array integer (batasi user scope)
- `shiftCategory` enum `office_hour|shifting_24h|hybrid`
- `rules` object:
  - `max_work_days_per_week` int 1..7
  - `min_days_off_per_week` int 0..7
  - `min_rest_hours_between_shifts` int 1..24
  - `max_consecutive_night_shifts` int 1..7
  - `illegal_transition_rules` array string (legacy key format, contoh: `night_to_morning`)
  - `late_tolerance_minutes` int 0..120
  - `early_leave_tolerance_minutes` int 0..120
  - `overtime_threshold_minutes` int 0..480
- `forbiddenTransitions` array string (format baru `from:to`, contoh: `night:morning`)
- `coverageRequirements` array:
  - `date` (date)
  - `required[]` dengan `shift_id` (string) dan `headcount` (int >= 0)

Catatan runtime:
- Rules runtime di-generate dari merge `rules` payload + default tenant pada endpoint settings.
- `forbiddenTransitions` (format `from:to`) akan dipetakan ke legacy `illegal_transition_rules` agar kompatibel dengan engine scheduler lama.

Success `200`:
- `data.schedule_generation`
  - `validation_status` (`valid|invalid`)
  - `weekly_schedule[]` per employee
  - `violations[]`
  - `unmet_coverage[]`
- `data.attendance_analysis`
  - `employee_summaries[]`
  - `flags[]`
- `data.recommendation`
  - `fairness_score` (0..100)
  - `fatigue_risk_score` (0..100)
  - `improvement_suggestions[]`
- `data.explanation` (human-readable summary)

Errors:
- `422 TENANT_CONTEXT_REQUIRED` jika context company tidak ada
- `422 NO_EMPLOYEE_IN_SCOPE` jika tidak ada employee dalam scope tenant/filters
- `403 AUTH_FORBIDDEN` untuk user non-admin

### GET `/smart-attendance-shifting/settings`

RBAC:
- HCM Admin only

Tujuan:
- Mengambil default planner rules per-tenant + daftar forbidden transition yang dipakai UI matrix.

Status identifier (transisi, tidak mengubah kontrak request):
- Tenant context request tetap mengikuti runtime context aktif (`X-Company-Id` numeric legacy dari session/header resolver).
- Persistence settings sekarang dual-write `company_id` + `company_uuid` agar tenant relation tetap bisa ditegakkan pada environment UUID-cutover.
- Actor audit settings juga dual-write `created_by_user_id`/`updated_by_user_id` + `created_by_user_uuid`/`updated_by_user_uuid`.

Success `200`:
- `data.defaultRules`
  - `max_work_days_per_week` int
  - `min_days_off_per_week` int
  - `min_rest_hours_between_shifts` int
  - `max_consecutive_night_shifts` int
- `data.forbiddenTransitions[]` string format `from:to`
- `data.transitionCatalog[]` string format `from:to`

### PUT `/smart-attendance-shifting/settings`

RBAC:
- HCM Admin only

Body:
- `defaultRules` object (opsional, field sama seperti GET response)
- `forbiddenTransitions` array string format `from:to`

Catatan kompatibilitas:
- Payload API tetap sama (tidak menambah field baru di request/response).
- Perubahan ada di persistence layer: backend menyimpan identifier legacy + UUID secara paralel untuk menghindari gap FK saat `companies.id`/`users.id` bukan key unik utama.

Success `200`:
- `data.defaultRules`
- `data.forbiddenTransitions[]`
- `data.transitionCatalog[]`

### POST `/smart-attendance-shifting/publish-roster`

RBAC:
- HCM Admin only

Tujuan:
- Publish hasil draft planner menjadi roster harian bertanggal (`hcm_schedule_rosters`) per kombinasi company+user+work_date.

Body:
- `weeklySchedule[]`
  - `employee_id` integer/string numeric
  - `assignments[]`
    - `date` (date, required)
    - `shift_id` (string; `OFF` untuk hari libur)
    - `start_time` (nullable string `H:i`)
    - `end_time` (nullable string `H:i`)
    - `cross_day` (boolean, optional)

Success `200`:
- `data.created` int
- `data.updated` int
- `data.offDays` int
- `data.total` int

### POST `/smart-attendance-shifting/simulate-swap`

RBAC:
- HCM Admin only

Tujuan:
- Simulasi tukar jadwal (swap) antara dua karyawan pada tanggal berbeda.
- Menghitung risiko fatigue, illegal transition, dan night streak setelah swap.

Body:
- `userAId` integer (required) — ID karyawan A
- `userBId` integer (required) — ID karyawan B
- `swapDateA` date (required) — tanggal jadwal karyawan A yang ditukar
- `swapDateB` date (required) — tanggal jadwal karyawan B yang ditukar

Success `200`:
- `data.swappable` boolean
- `data.swap_summary` string — deskripsi singkat swap
- `data.overall_risk_level` int (0=aman, 1=perlu perhatian, 2=berisiko tinggi)
- `data.employee_a` `{ name, original_shift, new_shift, risk_level, warnings[] }`
- `data.employee_b` `{ name, original_shift, new_shift, risk_level, warnings[] }`
- `data.warnings[]` string — peringatan gabungan
- `data.advice` string — rekomendasi tindak lanjut
- `data.reason` string — alasan jika `swappable=false`

---

### POST `/smart-attendance-shifting/find-replacement`

RBAC:
- HCM Admin only

Tujuan:
- Mencari kandidat pengganti karyawan yang tidak hadir/mendadak absen pada shift tertentu.
- Kandidat diurutkan berdasarkan skor kesesuaian (fatigue rendah, tidak ada konflik jadwal, bukan karyawan resigned/cuti).

Body:
- `absentUserId` integer (required) — ID karyawan yang absen
- `absentDates` date[] (required) — tanggal-tanggal absen
- `shiftId` string (required) — ID shift yang perlu diisi

Success `200`:
- `data.message` string — ringkasan hasil pencarian
- `data.candidates[]`
  - `employee_id` int
  - `employee_name` string
  - `job_title` string nullable
  - `reason` string — alasan kesesuaian kandidat

---

### GET `/schedule-rosters`

RBAC:
- HCM Admin only

Query:
- `dateFrom` date (required)
- `dateTo` date (required, harus >= `dateFrom`)
- `employeeIds[]` int (optional)
- `page` int (optional)
- `perPage` int (optional)

Success `200`:
- `data[]`
  - `id`
  - `userId`
  - `workDate`
  - `rosterStatus` (`working|off`)
  - `shiftId` nullable int
  - `shift` object nullable (`id`, `name`, `shiftType`, `startTime`, `endTime`)
- `meta.pagination` (page/perPage/total/totalPages)

