# Reporting System - Implementation

## Overview

Reporting module memakai dua surface runtime yang masih aktif:

- snapshot HCM immutable di `/v1/hcm/reports/snapshots*`;
- legacy report API di `/v1/saas/reports/*` yang masih menjadi sumber data untuk beberapa halaman report lama.

Untuk flow HCM tenant-scoped, kedua surface sekarang menghormati `X-Company-Id` sebagai tenant aktif.

## Architecture

- Snapshot metadata disimpan di `report_snapshots`
- Payload granular disimpan di `report_data_blocks`
- Filter request disimpan di `report_filters`
- Riwayat export disimpan di `report_exports`

### Main Components

- `backend/app/Http/Controllers/Api/ReportSnapshotController.php`
- `backend/app/Http/Controllers/Api/ReportController.php`
- `backend/app/Services/Reporting/ReportSnapshotService.php`
- `backend/app/Jobs/Reporting/GenerateReportSnapshot.php`
- `backend/app/Models/ReportSnapshot.php`
- `backend/app/Models/ReportDataBlock.php`
- `backend/app/Models/ReportFilter.php`
- `backend/app/Models/ReportExport.php`

## API Contract

Base: `/v1/hcm/reports/snapshots`

- `POST /` generate snapshot (`attendance|payroll|employee|leave|finance`)
- `GET /` list snapshots
- `GET /{id}` detail snapshot + grouped blocks (`dataByModule`) dengan identifier UUID atau numeric legacy fallback
- `POST /{id}/export` generate export file (`csv|excel|pdf`) dan simpan ke disk publik dengan identifier UUID atau numeric legacy fallback

Legacy report API:

- `GET /v1/saas/reports/revenue`
- `GET /v1/saas/reports/aging`
- `GET /v1/saas/reports/churn`

Ketiga endpoint legacy ini tetap backward compatible untuk consumer global lama, tetapi ketika request membawa `X-Company-Id` dari HCM page flow, backend sekarang:

- mengunci query ke company aktif pada header tersebut;
- menolak `company_id` query yang berbeda (`403 TENANT_SCOPE_MISMATCH`).

Response envelope konsisten:

- success: `true|false`
- data: payload sukses
- error: `{ code, message }` saat gagal

## Export Generation

`POST /{id}/export` sekarang menghasilkan file nyata:

- CSV: writer `fputcsv`
- Excel: tab-separated payload disimpan sebagai `.xls`
- PDF: HTML table dirender via `dompdf/dompdf`

Storage target:

- Disk: `public`
- Path: `report-exports/company_<companyId>/snapshot_<snapshotId>/<filename>`
- URL: via `Storage::url(...)` (contoh `/storage/report-exports/...`)

## Live vs Archive Integration

### Attendance

- View: `backend/resources/views/attendance-report.blade.php`
- JS: `frontend/resources/js/attendance-data.js`
- Mode `archive` membaca `/v1/hcm/reports/snapshots/{id}`

### Payslip

- View: `backend/resources/views/payslip-report.blade.php`
- JS: `frontend/resources/js/payslip-admin-data.js`
- Mode `archive` menormalisasi blok payroll (`user_*`)

### Employee

- View: `backend/resources/views/employee-report.blade.php`
- JS: `frontend/resources/js/employees-data.js`
- Mode `archive` membaca blok `employee.summary` + `employee.by_status`

### Leave

- View: `backend/resources/views/leave-report.blade.php`
- JS: `frontend/resources/js/hcm-extras-data.js`
- Mode `archive` membaca blok `leave.summary`, `leave.by_status`, dan `leave.user_*`

## Security Notes

- Semua endpoint snapshot memerlukan bearer auth + tenant context aktif
- Permission check server-side memakai `EnsuresHcmAdmin`
- Non-admin selalu menerima `403 AUTH_FORBIDDEN`
- Legacy report API mempertahankan permission `report.view`, tetapi bila `X-Company-Id` dikirim maka company scope tidak boleh dioverride lewat query string.

## Tenant Wiring

- Frontend reporting requests sekarang mengirim tenant context dari `AuthApi.getTenantContext()` agar cocok dengan backend guard berbasis `X-Company-Id`.
- `ReportsHub` diekspor ke `window` supaya aksi inline di blade tetap bekerja tanpa bergantung pada scope module.
- Wiring mismatch yang ditemukan selama validasi sudah diperbaiki dan divalidasi lagi dengan Vitest serta PHPUnit.

## Tests

Regression tests ada di:

- `backend/tests/Feature/ReportSnapshotApiTest.php`
- `backend/tests/Feature/ReportControllerTest.php`
- `backend/tests/ui/reports-api-sync.wiring.test.js`
- `backend/tests/ui/reports-hub.wiring.test.js`

Coverage utama:

- Generate/list snapshot (admin)
- Forbidden generate (non-admin)
- Show snapshot detail
- Show snapshot detail by UUID
- Export file nyata untuk `csv`, `excel`, `pdf`
- Edge case export: `SNAPSHOT_NOT_READY`, `SNAPSHOT_NOT_FOUND`
- Cross-company snapshot isolation (`404` bila snapshot milik company lain)
- Legacy report tenant hardening (`TENANT_SCOPE_MISMATCH` bila query mencoba override `X-Company-Id`)
- Tenant/auth wiring untuk reporting frontend dan guard backend

## Known Limits

- Payload export saat ini flatten generic (`module`, `dataKey`, `payload JSON`) belum report-specific BI shape.
- Excel writer sengaja ringan (`.xls` tab-separated) untuk kompatibilitas cepat tanpa kompleksitas workbook multi-sheet.
