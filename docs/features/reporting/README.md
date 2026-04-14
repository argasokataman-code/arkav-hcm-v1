# Reporting System

## Scope

Snapshot-based reporting module untuk HCM/ERP dengan dukungan multi-tenant, generate async/sync, dan konsumsi data archive dari halaman report yang sudah ada.

Target utama:
- Simpan point-in-time report ke snapshot immutable
- Pisahkan metadata snapshot vs data blocks JSON
- Sediakan API generate/list/detail/export snapshot
- Integrasikan mode `Live` vs `Archive` pada halaman report aktif tanpa redesign besar

## Status

Status: In Progress (Phase 3)
Version: 0.3
Last updated: 2026-04-13

### Progress Matrix

| Component | Status | Notes |
|---|---|---|
| DB migration reporting tables | ✅ Done | `report_snapshots`, `report_data_blocks`, `report_filters`, `report_exports` sudah migrated |
| Models reporting | ✅ Done | `ReportSnapshot`, `ReportDataBlock`, `ReportFilter`, `ReportExport` |
| Service snapshot generator | ✅ Done | `ReportSnapshotService` dengan report type: attendance/payroll/employee/leave/finance |
| Queue job snapshot | ✅ Done | `GenerateReportSnapshot` (ShouldQueue) |
| API controller snapshot | ✅ Done | generate, list, show, export |
| API routes snapshot | ✅ Done | `/v1/hcm/reports/snapshots*` |
| Reports hub page | ✅ Done | route web `/reports` + `reports/hub.blade.php` |
| Reports hub JS wiring | ✅ Done | `reports-hub.js` (generate/list/export trigger) |
| Live/Archive toggle: attendance report | ✅ Done | `attendance-report.blade.php` + `attendance-data.js` |
| Live/Archive toggle: payslip report | ✅ Done | `payslip-report.blade.php` + `payslip-admin-data.js` |
| Live/Archive toggle: employee report | ✅ Done | `employee-report.blade.php` + `employees-data.js` |
| Live/Archive toggle: leave report | ✅ Done | `leave-report.blade.php` + `hcm-extras-data.js` |
| Export real file generation | ✅ Done | Controller export now writes real `csv`, `xls`, `pdf` ke storage publik |
| OpenAPI sync | ✅ Done | `docs/api/openapi.yaml` sudah ditambah endpoint Reporting |
| Regression tests snapshot API | ✅ Done | `ReportSnapshotApiTest` diperluas untuk show/export + edge cases |

## Implemented API Surface

Base path: `/v1/hcm/reports/snapshots`

- `POST /` generate snapshot (`reportType`, `periodStart`, `periodEnd`, `filters`, `async`)
- `GET /` list snapshots (filter + pagination)
- `GET /{id}` detail snapshot + data blocks
- `POST /{id}/export` create export record (`fileType: csv|excel|pdf`)

Auth model:
- Auth: Bearer token
- Tenant context: `X-Company-Id`
- Authorization: HCM Admin only (server-side enforced)

## Validation & Build

- `php artisan test --filter=ReportSnapshotApiTest` → PASS (2 tests, 16 assertions)
- `npm run build` → PASS
- JS artifacts updated di `backend/public/build/js` termasuk:
  - `attendance-data.js`
  - `payslip-admin-data.js`
  - `reports-hub.js`

## Current Behavior Notes

- Reports Hub:
  - `View` membuka halaman report yang menampilkan source selector `Live` vs `Archive Snapshot`
  - `Live` memuat data dari API modul aktif
  - `Archive` memuat data dari `/v1/hcm/reports/snapshots/{id}` menggunakan Snapshot ID
  - Banner info di hub menjelaskan bahwa `Generate` membuat snapshot untuk dipakai ulang
- Attendance report:
  - `Live` mode tetap konsumsi `/v1/hcm/attendance/admin`
  - `Archive` mode konsumsi `/v1/hcm/reports/snapshots/{id}` lalu normalisasi block attendance
- Payslip report:
  - `Live` mode tetap konsumsi `/v1/hcm/payroll/admin-slips`
  - `Archive` mode konsumsi snapshot payroll lalu normalisasi block `user_*`
- Reports Hub:
  - Bisa trigger generate snapshot
  - Bisa list snapshot terbaru
  - Bisa trigger export record

## Next Phase (Phase 3)

1. Hardening export payload format untuk kebutuhan BI downstream (opsional flatten per reportType)
2. Tambahkan halaman history/export browser jika diperlukan user non-teknis
3. Jalankan full E2E multi-role untuk Live/Archive flow di staging

## Documentation Links

- [IMPLEMENTATION.md](IMPLEMENTATION.md) - Arsitektur teknis, data flow, endpoint, dan storage contract
- [E2E-TESTING.md](E2E-TESTING.md) - Skenario manual UI E2E untuk admin/non-admin
