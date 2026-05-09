# Payroll Runs — Implementation

Status: Implemented (Payroll Period + Run + Finalize + Disburse + Mock Payment)
Updated: 2026-05-08

Tracker: [tracker.md](tracker.md)

## Overview

Surface payroll run runtime mencakup: manajemen periode payroll, kalkulasi draft run, finalisasi, export reconciliation sebagai gate disburse, mock hosted payment flow, disburse batch, void, dan histori run. Self-service slip lines untuk employee juga disediakan.

## Controllers

- `backend/app/Http/Controllers/Api/HcmPayrollPeriodController.php`
- `backend/app/Http/Controllers/Api/HcmPayrollRunController.php`
- `backend/app/Http/Controllers/Api/HcmPayrollThrController.php`
- `backend/app/Http/Controllers/Api/HcmPayrollThrBatchController.php`
- `backend/app/Http/Controllers/Api/HcmPayrollThrSettingsController.php`
- `backend/app/Http/Controllers/Api/HcmPayrollPkwtCompensationController.php`
- `backend/app/Http/Controllers/Api/HcmPayrollSettingsController.php`
- `backend/app/Http/Controllers/Api/HcmPayrollWorkArrangementController.php`

## Web Surfaces

- `backend/resources/views/payroll-run.blade.php` — halaman run periode aktif (admin)
- `backend/resources/views/payroll-run-history.blade.php` — histori run (admin)
- `backend/resources/views/payroll-thr.blade.php` — THR run (admin)
- `backend/resources/views/payroll-pkwt-compensation.blade.php` — PKWT compensation (admin)
- `backend/resources/views/payslip.blade.php` — self-service slip employee

## Route File

`backend/routes/api/payroll.php` — prefix `v1/hcm`, middleware: `hcm.api.feature:payroll`

## Main API Endpoints

### Payroll Periods
- `GET /v1/hcm/payroll-periods` — daftar periode
- `GET /v1/hcm/payroll-periods/active` — periode aktif saat ini
- `POST /v1/hcm/payroll-periods` — buat periode baru
- `GET /v1/hcm/payroll-periods/{id}` — detail periode
- `POST /v1/hcm/payroll-periods/{id}/calculate-draft` — hitung draft run untuk periode

### Payroll Runs
- `GET /v1/hcm/payroll-runs/history` — histori run
- `GET /v1/hcm/payroll-runs/{id}` — detail run
- `POST /v1/hcm/payroll-runs/{id}/finalize` — finalisasi run
- `POST /v1/hcm/payroll-runs/{id}/void` — void run finalized (sebelum ada line paid)
- `POST /v1/hcm/payroll-runs/{id}/mock-hosted-checkout` — mulai mock payment flow
- `POST /v1/hcm/payroll-runs/{id}/mock-hosted-checkout/confirm` — konfirmasi mock payment
- `POST /v1/hcm/payroll-runs/{id}/disburse` — disburse batch setelah payment confirmed
- `POST /v1/hcm/payroll-runs/{id}/reset-payments` — reset pembayaran (dev/testing)

### Self-Service Slip (employee)
- `GET /v1/hcm/payroll/my-slip` — slip ringkas bulanan
- `GET /v1/hcm/payroll/my-slip-lines` — baris slip detail
- `GET /v1/hcm/payroll/my-slip-pdf` — unduh PDF slip
- `GET /v1/hcm/payroll/admin-run-slips` — semua slip dalam satu run (admin)
- `GET /v1/hcm/payroll/admin-slips` — semua slip (admin)
- `POST /v1/hcm/payroll/send-slips` — kirim slip bulanan via email

### THR
- `GET /v1/hcm/payroll/my-thr-slip` — THR slip employee
- `POST /v1/hcm/payroll/thr-calculate` — hitung THR draft
- `GET/PUT /v1/hcm/payroll/thr-settings/{calendarYear}` — settings THR per tahun
- `GET /v1/hcm/payroll/thr-batch` — detail THR batch aktif
- `POST /v1/hcm/payroll/thr-batch/generate` — generate THR batch
- `POST /v1/hcm/payroll/thr-batch/disburse` — disburse THR
- `POST /v1/hcm/payroll/thr-batch/post-payroll` — post ke payroll history
- `POST /v1/hcm/payroll/thr-batch/send-slip` — kirim THR slip
- `GET /v1/hcm/payroll/thr-batch/lines/{line}/slip` — unduh PDF THR slip per baris

### PKWT Compensation
- `GET /v1/hcm/payroll/pkwt-compensations` — list PKWT compensation
- `POST /v1/hcm/payroll/pkwt-calculate` — hitung PKWT compensation
- `POST /v1/hcm/payroll/pkwt-compensations/post-payroll` — post ke payroll

### Settings & Work Arrangements
- `GET/PUT /v1/hcm/payroll/settings` — payroll settings tenant
- `GET/POST /v1/hcm/payroll/work-profiles` — profil kerja
- `PUT /v1/hcm/payroll/work-profiles/{id}` — update profil kerja
- `GET/POST /v1/hcm/payroll/work-arrangements` — work arrangement employee
- `PUT /v1/hcm/payroll/work-arrangements/{id}` — update arrangement

## Data Models

- `HcmPayrollPeriod` — periode payroll (status: `open|posted`)
- `HcmPayrollRun` — run per periode (status: `draft|finalized|void|posted`)
- `HcmPayrollLine` — baris run per karyawan (status: `pending|paid`)
- `HcmPayrollWorkProfile` — profil pengaturan kerja

## Lifecycle Run

- `draft` → kalkulasi ulang boleh, export reconciliation wajib sebelum disburse
- `finalized` → dapat di-void jika belum ada line `paid`
- `posted` → setelah disburse; histori final
- `void` → kembali ke periode `open`, draft bisa dihitung ulang

## Export Reconciliation Gate

Export reconciliation dengan `actionKey=disburse` harus diunduh (evidence sukses) sebelum tombol disburse aktif. Backend memvalidasi flag ini sebelum memproses batch disburse.
