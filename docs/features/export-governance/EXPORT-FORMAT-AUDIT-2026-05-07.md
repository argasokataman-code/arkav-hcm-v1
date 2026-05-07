# Export Format Audit - 2026-05-07

## Tujuan

Menginventaris seluruh endpoint export API yang relevan, memastikan format output aktual, dan memetakan gap terhadap target standar: tabular export default `xlsx`.

## Ringkasan Hasil

- Total endpoint export utama yang teridentifikasi: 11
- Sudah default `xlsx`: 1 (employee domain)
- Sudah dukung `xlsx` tapi default belum `xlsx`: 0
- Masih `csv` only: 0 (untuk scope endpoint prioritas migrasi 2026-05-07)
- Non-tabular attachment `json`: 2
- Multi-format (`csv/excel/pdf`): 1
- Regulatory CSV khusus: 1

## Detail Endpoint

| Domain | Endpoint | Controller Method | Format Saat Ini | Temuan |
|---|---|---|---|---|
| Employee | `/v1/hcm/employees/export` | `HcmEmployeeController::exportEmployees` | `xlsx` default, support `csv/pdf` | Sudah paling dekat standar |
| Employee | `/v1/hcm/departments/export` | `HcmEmployeeController::exportDepartments` | `xlsx` default, support `csv/pdf` | Sudah paling dekat standar |
| Employee | `/v1/hcm/designations/export` | `HcmEmployeeController::exportDesignations` | `xlsx` default, support `csv/pdf` | Sudah paling dekat standar |
| Employee | `/v1/hcm/policies/export` | `HcmEmployeeController::exportPolicies` | `xlsx` default, support `csv/pdf` | Sudah paling dekat standar |
| Payroll | `/v1/hcm/payroll-items/export` | `HcmPayrollItemController::export` | support `xlsx/csv`, default `xlsx` | Sudah sesuai standar tabular |
| Leave | `/v1/hcm/leave-requests/export` | `HcmLeaveRequestController::export` | support `xlsx/csv`, default `xlsx` | Sudah sesuai standar tabular |
| User Management | `/v1/hcm/user-management/users/export` | `HcmUserManagementController::usersExport` | support `xlsx/csv`, default `xlsx` | Sudah sesuai standar tabular |
| Notifications | `/v1/hcm/notifications/delivery-export` | `HcmNotificationController::exportDeliveries` | support `xlsx/csv`, default `xlsx` | Sudah sesuai standar tabular |
| SaaS Billing | `/v1/saas/transactions/export` | `TransactionController::export` | support `xlsx/csv`, default `xlsx` | Sudah sesuai standar tabular |
| Reporting | `/v1/hcm/reports/snapshots/{id}/export` | `ReportSnapshotController::export` | `csv/excel/pdf` | Sudah multi-format, naming param pakai `fileType` |
| Tax SPT Masa | `/v1/hcm/spt-masa/headers/{sptRef}/export.csv` | `HcmSptMasaController::exportCsv` | `csv` only | Pengecualian regulatori (DJP-style CSV) |

## Endpoint Export Attachment Non-Tabular

| Domain | Endpoint | Format | Catatan |
|---|---|---|---|
| BPJS Governance | `/v1/hcm/bpjs-governance/reports/export` | `json` attachment | Bukan tabular sheet, perlu keputusan produk apakah dimigrasi ke `xlsx` |
| Allowance Governance | `/v1/hcm/allowance-governance/reports/compliance/export` | `json` attachment | Bukan tabular sheet, perlu keputusan produk apakah dimigrasi ke `xlsx` |

## Frontend Indicator (Sampling)

Ditemukan beberapa script UI yang masih hardcode output `.csv` (contoh: attendance extras, user management client-side export, notifications observability, tickets, goals, FAQ). Ini menunjukkan standar export lintas UI belum seragam.

## Risiko Jika Tidak Diseragamkan

1. Inconsistent UX: user bingung karena format file berbeda antar modul.
2. Maintainability rendah: setiap modul implement logic export sendiri.
3. Compliance risk: audit trail dan policy export sulit dijaga konsisten.
4. Regression risk: modul baru cenderung meniru pola lama yang tidak standar.

## Baseline Keputusan Teknis

1. Semua export data tabular API: default `xlsx`.
2. `csv` tetap opsional untuk kompatibilitas (explicit request).
3. Pengecualian regulatory CSV harus terdokumentasi eksplisit.
4. Implement helper shared untuk response builder export.

## Backlog Lanjutan Disarankan

1. Perluas adopsi helper shared `TabularExportResponse` ke endpoint tabular lain di luar scope ini.
2. Standarkan parameter request (`format`) dan response header lintas seluruh endpoint export tersisa.
3. Sinkronkan semua halaman UI export yang masih hardcode `.csv` di modul non-priority.
4. Tambahkan test matrix export per modul: auth, tenant scope, format, header, filename.
