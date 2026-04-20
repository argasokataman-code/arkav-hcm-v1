# Attendance, Shift, and Schedule - Implementation

## Overview

Feature ini menutupi tiga surface admin yang saling terkait:

- master shift (`/shifts` API dan UI terkait)
- jadwal per-user (`/schedule-timing`)
- timesheets admin (`/timesheets`)

Runtime utamanya masih berada di bundle `frontend/resources/js/attendance-data.js` untuk timesheets + schedule timing, dan `frontend/resources/js/shift-master-data.js` untuk master shift.

## Web Surfaces

- `backend/resources/views/schedule-timing.blade.php`
- `backend/resources/views/timesheets.blade.php`
- surface admin attendance yang memicu mutasi jadwal dan koreksi attendance

## API Contract

Source of truth:
- `docs/api/hcm-shift-schedule-api.md`
- `docs/api/hcm-attendance-api.md`

Endpoint aktif:
- `GET /v1/hcm/shifts`
- `POST /v1/hcm/shifts`
- `PUT /v1/hcm/shifts/{id}`
- `DELETE /v1/hcm/shifts/{id}`
- `GET /v1/hcm/schedule-timing`
- `PUT /v1/hcm/schedule-timing/{userId}`
- `DELETE /v1/hcm/schedule-timing/{userId}`
- `GET /v1/hcm/timesheets`

## Identifier Rules

- `PUT /schedule-timing/{userId}` memakai numeric `users.id` untuk target user.
- `shiftId` dalam payload schedule timing memakai numeric `hcm_shifts.id`.
- Backend menolak write jika target user bukan member tenant aktif.
- Timesheets date range mengikuti aturan `dateTo >= dateFrom`; range terbalik harus ditolak sebelum query berat dijalankan.

## Data Model

Tabel utama:
- `hcm_shifts`
- `hcm_schedule_timings`
- `attendance_records`
- `users`
- `company_users`

Relasi penting:
- `hcm_schedule_timings.user_id -> users.id`
- `hcm_schedule_timings.hcm_shift_id -> hcm_shifts.id` (opsional saat override manual)
- timesheets membaca agregasi dari `attendance_records` dan user profile tenant aktif

## Frontend Notes

- `attendance-data.js` mengelola list timesheet, validasi filter, modal/edit schedule timing, dan submit update schedule.
- `shift-master-data.js` mengelola CRUD shift master.
- Validasi frontend tambahan yang sudah aktif:
  - date range timesheets diblok jika `dateTo < dateFrom`
  - schedule timing submit mengirim `shiftId` numeric sesuai kontrak backend

## Tenant Scope

- Query shift, schedule timing, dan timesheet dibatasi ke company aktif.
- Mutation schedule timing menolak target user lintas tenant.
- Timesheets admin hanya surface baca untuk tenant aktif dan tidak boleh memakai parameter yang membypass company scope.

## Tests

Backend:
- `backend/tests/Feature/AttendanceApiTest.php`

Frontend/Vitest:
- `backend/tests/ui/attendance.wiring.test.js`

Coverage penting:
- schedule timing write memakai numeric `shiftId`
- schedule timing write menolak target user di luar tenant aktif
- timesheets reversed date range ditolak

## Known Limits

- Timesheets saat ini fokus pada admin monitoring, belum workflow approval timesheet terpisah.
- Override manual schedule masih single-record per user tanpa history audit yang kaya di UI.
- Shift master dan schedule timing masih dipisah bundle frontend sehingga cross-page regression perlu dicek saat salah satu berubah.
