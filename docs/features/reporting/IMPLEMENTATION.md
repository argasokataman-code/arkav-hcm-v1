# Reporting System - Implementation

## Overview

Reporting module memakai pola snapshot immutable untuk membekukan data laporan pada periode tertentu. Endpoint berada di `/v1/hcm/reports/snapshots*` dengan tenant isolation (`X-Company-Id`) dan admin-only authorization.

## Architecture

- Snapshot metadata disimpan di `report_snapshots`
- Payload granular disimpan di `report_data_blocks`
- Filter request disimpan di `report_filters`
- Riwayat export disimpan di `report_exports`

### Main Components

- `backend/app/Http/Controllers/Api/ReportSnapshotController.php`
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
- `GET /{id}` detail snapshot + grouped blocks (`dataByModule`)
- `POST /{id}/export` generate export file (`csv|excel|pdf`) dan simpan ke disk publik

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

## Tests

Regression tests ada di:

- `backend/tests/Feature/ReportSnapshotApiTest.php`

Coverage utama:

- Generate/list snapshot (admin)
- Forbidden generate (non-admin)
- Show snapshot detail
- Export file nyata untuk `csv`, `excel`, `pdf`
- Edge case export: `SNAPSHOT_NOT_READY`, `SNAPSHOT_NOT_FOUND`

## Known Limits

- Payload export saat ini flatten generic (`module`, `dataKey`, `payload JSON`) belum report-specific BI shape.
- Excel writer sengaja ringan (`.xls` tab-separated) untuk kompatibilitas cepat tanpa kompleksitas workbook multi-sheet.
