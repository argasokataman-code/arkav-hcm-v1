# Overtime API (Phase 1)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/HcmOvertimeTypeController.php` + `backend/app/Http/Controllers/Api/HcmOvertimeRequestController.php`.

## Integrasi master komponen gaji

- Setiap **POST** `/overtime-requests` mengisi **`hcm_salary_component_id`** ke komponen slip untuk upah lembur, di-resolve lewat `HcmSalaryComponent::resolveForOvertimePay()` (prioritas: baris aktif `code = upah_lembur`, fallback: `kind = addition` + `category = overtime`).
- **GET** `/overtime-requests` menyertakan per baris: `salaryComponentId`, `salaryComponentCode`, `salaryComponentName` (bisa `null` jika master dihapus/nonaktif dan tidak ada fallback).
- **POST** `/overtime-requests/calculate` menambahkan field **`salaryComponent`** (`id`, `code`, `name` atau `null`) agar UI kalkulator selaras dengan definisi slip.
- Pemilik request yang **mengubah** pengajuan `pending` (PUT sebagai owner) akan **refresh** tautan komponen ke resolver terkini.

## Base path

`/v1/hcm`

## Overtime Types (Master)

### GET `/overtime-types`

RBAC:
- Authenticated: allowed
- Non-admin: hanya `is_active=true`

### POST `/overtime-types`

RBAC:
- HCM Admin only

Body:
- `name` required string max 200
- `code` optional string max 64 regex `^[a-z0-9_-]+$` (unique; duplicate → 422)
- `description` optional string max 500
- `paymentMultiplier` optional numeric 0.01..99.99 (default 1)
- `isActive` optional boolean
- `sortOrder` optional int 0..65535

Success `201`: `{ success: true, data: { id } }`

### PUT `/overtime-types/{id}`

RBAC:
- HCM Admin only

Body:
- sama seperti POST, tapi `paymentMultiplier` **required**

### DELETE `/overtime-types/{id}`

RBAC:
- HCM Admin only

## Overtime Requests

Status:
- `pending|approved|declined`

Request type:
- `employee_request|company_assignment|missed_log_correction`

### GET `/overtime-requests`

Query:
- `scope=me` optional (untuk admin filter self)
- `page` optional int ≥1
- `perPage` optional int 1..100 (default 20)
- `workDate` optional date — filter satu hari (`work_date`); jika ada, `dateFrom`/`dateTo` diabaikan
- `dateFrom` optional date — batas bawah `work_date` (inklusif)
- `dateTo` optional date — batas atas `work_date` (inklusif)
- `status` optional `pending|approved|declined`

RBAC:
- HCM Admin: all (kecuali scope=me)
- Non-admin: hanya own

Response:
- `data[]` — halaman hasil; tiap elemen termasuk `salaryComponentId`, `salaryComponentCode`, `salaryComponentName` (tautan ke `hcm_salary_components` untuk payroll slip)
- `meta.pagination`: `page`, `perPage`, `total`, `totalPages`
- `meta.summary` (admin, tanpa `scope=me`): `distinctUsers`, `pending`, `declined`, `approvedMinutes` — **dihitung dengan filter tanggal/status yang sama** seperti daftar (bukan global seluruh DB)

### POST `/overtime-requests`

Body:
- `userId` optional int exists users.id (admin only; default self)
- `overtimeTypeId` optional int exists hcm_overtime_types.id (non-admin hanya active)
- `requestType` optional enum (non-admin only `employee_request`)
- `workDate` required date
- `minutes` required int 1..1440
- `status` optional enum (admin only; non-admin forced `pending`)
- `projectName` optional string max 200
- `notes` optional string max 2000
- `policyNote` optional string max 500

RBAC / guard:
- non-admin set `userId` ≠ self → 403 `AUTH_FORBIDDEN`
- non-admin set `requestType` selain `employee_request` → 403

Success `201`: `{ success: true, data: { id } }`

### PUT `/overtime-requests/{id}`

RBAC:
- Owner: editable hanya saat `pending` else `422 OT_NOT_EDITABLE`
- Admin: jika update request orang lain, body khusus status+notes

Admin update (when actor != owner):
- body `status` required enum
- `notes` optional

Owner update (when actor == owner):
- boleh update field seperti POST (tanpa `userId/status`)
- non-admin tidak boleh set requestType selain `employee_request`

### DELETE `/overtime-requests/{id}`

RBAC:
- Owner only; delete orang lain → `403 FORBIDDEN`
- hanya `pending` yang bisa dihapus → `422 OT_NOT_DELETABLE`

Kontrak penting (update):
- `overtimeTypeId` (optional FK) pada create/update
- `requestType`: `employee_request|company_assignment|missed_log_correction`
- Non-admin hanya boleh pilih overtime type yang aktif

## Overtime Calculator

### POST `/overtime-requests/calculate`

Body:
- `baseMonthlySalary` required numeric min 0
- `fixedAllowance` optional numeric min 0
- `minutes` required int 1..1440
- `dayType` required `workday|holiday`
- `weeklyWorkDays` optional int `5|6` (default 5)

Success `200`:
- `data` berisi hasil kalkulasi (breakdown + totals) dari `OvertimePayCalculator`, plus **`salaryComponent`**: `{ id, code, name } | null` (sama resolver dengan create request)

