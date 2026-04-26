# Payroll Work Arrangement API (Shift vs Office Configurator)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/HcmPayrollWorkArrangementController.php`.

## Tujuan

Membuat payroll lembur configurable berdasarkan pola kerja karyawan:
- `office_hour`
- `shift_worker`

Konfigurasi disimpan dalam dua layer:
1. **Work profile** (`hcm_payroll_work_profiles`) sebagai template kebijakan tenant.
2. **Employee arrangement** (`hcm_employee_work_arrangements`) sebagai assignment efektif per karyawan.

Payroll draft akan membaca assignment ini sebagai fallback rule saat `overtime_requests.day_type` / `weekly_work_days` tidak diisi.

## Base path

`/v1/hcm/payroll`

## Profiles

### GET `/work-profiles`

RBAC:
- `payroll.view`

Response item:
- `id`
- `code`
- `name`
- `arrangementMode` = `office_hour|shift_worker`
- `defaultDayType` = `workday|public_holiday|weekly_rest_day|weekly_rest_day_short`
- `weeklyWorkDays` = `5|6`
- `isDefault`
- `meta`

### POST `/work-profiles`

RBAC:
- `payroll.manage`

Body:
- `code` required snake/kebab `[a-z0-9_-]+`
- `name` required string
- `arrangementMode` required enum
- `defaultDayType` required enum
- `weeklyWorkDays` required `5|6`
- `isDefault` optional bool
- `meta` optional object

### PUT `/work-profiles/{id}`

RBAC:
- `payroll.manage`

Body (partial update):
- `name`
- `arrangementMode`
- `defaultDayType`
- `weeklyWorkDays`
- `isDefault`
- `meta`

## Employee Arrangements

### GET `/work-arrangements`

RBAC:
- `payroll.view`

Query:
- `userId` optional int
- `effectiveDate` optional date (mengambil arrangement aktif di tanggal tersebut)
- `arrangementMode` optional enum
- `perPage` optional int `1..100`

Response item:
- `id`
- `userId`, `userName`, `userEmail`
- `profileId`, `profileCode`, `profileName`
- `arrangementMode`
- `defaultDayType`
- `weeklyWorkDays`
- `effectiveFrom`, `effectiveTo`
- `notes`

### POST `/work-arrangements`

RBAC:
- `payroll.manage`

Body:
- `userId` required users.id
- `profileId` optional profile id
- `arrangementMode` required enum
- `defaultDayType` optional enum
- `weeklyWorkDays` optional `5|6`
- `effectiveFrom` required date
- `effectiveTo` optional date (`>= effectiveFrom`)
- `notes` optional string

### PUT `/work-arrangements/{id}`

RBAC:
- `payroll.manage`

Body (partial update):
- `profileId`
- `arrangementMode`
- `defaultDayType`
- `weeklyWorkDays`
- `effectiveFrom`
- `effectiveTo`
- `notes`

Validation khusus:
- jika `effectiveTo < effectiveFrom` => `422 WORK_ARRANGEMENT_DATE_INVALID`.
