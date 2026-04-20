# Attendance - Implementation

## Overview

Surface attendance runtime saat ini terkonsentrasi di satu controller API dan satu bundle frontend utama:

- API/controller: `backend/app/Http/Controllers/Api/AttendanceController.php`
- Routes API: `backend/routes/api.php`
- Web pages: `backend/routes/web.php`
- Frontend runtime: `frontend/resources/js/attendance-data.js`

Feature ini mencakup employee self attendance, admin attendance list/edit, attendance report, dan data lokasi attendance.

## Web Surfaces

- `backend/resources/views/attendance-employee.blade.php`
- `backend/resources/views/attendance-admin.blade.php`
- `backend/resources/views/attendance-report.blade.php`

Guard web:
- `/attendance-employee` dapat diakses employee/admin sesuai session HCM.
- `/attendance-admin` dan `/attendance-report` dijaga sebagai surface admin melalui web guard HCM.

## Main API Endpoints

### Employee

- `GET /v1/hcm/attendance/me/today`
- `GET /v1/hcm/attendance/me/history`
- `GET /v1/hcm/attendance/me/stats`
- `POST /v1/hcm/attendance/me/punch`
- `POST /v1/hcm/attendance/me/break`
- `POST /v1/hcm/attendance/me/correction-request`

### Admin

- `GET /v1/hcm/attendance/admin`
- `PUT /v1/hcm/attendance/admin/record`
- `GET /v1/hcm/attendance/admin/records/{id}/selfie/download`

## Tenant Scope

- `AttendanceController::applyTenantScope()` sekarang mengunci query ke `company_id` aktif.
- `adminUpsertRecord()` resolve target user dari identifier aktif lalu menolak target di luar membership company aktif dengan `404 USER_NOT_IN_COMPANY`.
- Attendance report live membaca `GET /attendance/admin` tenant aktif; archive report membaca snapshot HCM yang juga di-scope per company.

## Data Model

Tabel utama:
- `attendance_records`
- `company_users`

Kolom penting di `attendance_records`:
- `user_id`, `company_id`, `work_date`
- `check_in_at`, `check_out_at`
- `check_in_latitude`, `check_in_longitude`
- `check_out_latitude`, `check_out_longitude`
- `break_minutes`, `late_minutes`
- `status`, `correction_status`, `correction_reason`
- `check_in_location_name`, `check_in_location_address`, `check_in_location_source`
- `check_out_location_name`, `check_out_location_address`, `check_out_location_source`
- `selfie_path`, `selfie_encrypted_hash`

## Location Handling

Detail reverse geocoding disimpan di `LOCATION-FEATURE.md`, tetapi implementasi aktifnya bergantung pada:

- `App\Services\LocationService`
- fallback display di payload `me/today`, `history`, `admin`, dan report rows
- cache hasil reverse geocoding agar API publik Nominatim tidak dipukul berulang

## Reporting Integration

- Mode live di `/attendance-report` memanggil `GET /v1/hcm/attendance/admin?date=...`.
- Mode archive di `/attendance-report` memanggil `GET /v1/hcm/reports/snapshots/{id}`.
- Frontend menolak snapshot archive jika `reportType !== attendance` atau `status !== completed`.

## Frontend Notes

`attendance-data.js` menangani beberapa concern sekaligus:
- state employee attendance dan auto-refresh saat shift berjalan;
- admin attendance filter, pagination, correction detail, dan edit form;
- report attendance live/archive;
- timesheets dan schedule timing;
- selfie button gating berdasarkan `punchState`.

Implikasi praktisnya: perubahan di file ini perlu regression test sempit karena satu bundle memegang banyak halaman attendance.

## Tests

Backend:
- `backend/tests/Feature/AttendanceApiTest.php`
- `backend/tests/Feature/AttendanceAdminTenantScopeTest.php`
- `backend/tests/Feature/AttendanceSelfieTest.php`
- `backend/tests/Feature/WebHcmRouteGuardTest.php`

Frontend/Vitest:
- `backend/tests/ui/attendance.wiring.test.js`
- `backend/tests/ui/report-snapshot-selector.wiring.test.js`

Coverage penting yang sudah ada:
- auth/forbidden/admin attendance
- tenant-forbidden saat company override tidak valid
- admin write tenant guard (`USER_NOT_IN_COMPANY`)
- timesheet reversed date range ditolak
- report archive attendance menolak snapshot salah tipe / belum completed

## Known Limits

- Approval workflow koreksi masih minimal; request correction baru berhenti pada status requested.
- Bundle frontend attendance masih besar, sehingga perubahan kecil bisa berdampak ke beberapa halaman sekaligus.
- Evidence manual browser E2E untuk seluruh menu attendance belum diringkas dalam satu log eksekusi formal.
