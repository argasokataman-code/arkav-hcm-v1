# Export Governance — Implementation

Status: Implemented (Reconciliation Export + Evidence Tracking)
Updated: 2026-05-08

Tracking: [TRACKING.md](TRACKING.md)
Audit: [EXPORT-FORMAT-AUDIT-2026-05-07.md](EXPORT-FORMAT-AUDIT-2026-05-07.md)

## Overview

Export governance mengatur dan mengaudit semua proses export data kritis (terutama payroll reconciliation). Export bukan sekadar download file — setiap export menghasilkan evidence record yang dipakai sebagai gate untuk proses downstream seperti payroll disburse. Tanpa evidence export yang valid, disburse batch akan ditolak.

## Controllers

- `backend/app/Http/Controllers/Api/ReconciliationExportController.php`

## Web Surfaces

Export dipicu dari halaman-halaman terkait (payroll run, BPJS, allowance governance), bukan dari halaman khusus export governance.

## Route File

`backend/routes/api/reconciliation.php` — prefix `v1/reconciliation`, middleware: `api.token`, `tenant.context`

## Main API Endpoints

- `POST /v1/reconciliation/exports` — buat export baru (generate file + catat evidence)
- `GET /v1/reconciliation/exports` — daftar export (history evidence per tenant + action type)
- `GET /v1/reconciliation/exports/{id}/download` — unduh file export (streaming, auth-checked; mencatat flag download sukses)

## Data Models

- `ExportReconciliationEvidence` — evidence record export
  - `id` bigint PK
  - `company_id` — tenant scope
  - `action_key` — jenis action (contoh: `disburse`, `bpjs_report`, dll.)
  - `action_ref` — referensi ke entitas terkait (run ID, periode, dll.)
  - `file_path` — path file yang di-generate
  - `downloaded_at` — timestamp pertama kali file berhasil diunduh (gate key)
  - `generated_by` — user yang menginisiasi export

## Gate Pattern

Komponen lain (contoh: payroll disburse) mengecek apakah evidence export dengan `action_key=disburse` dan `action_ref` yang cocok sudah memiliki `downloaded_at` tidak null. Jika belum diunduh, backend menolak action downstream dengan error yang sesuai.

## Tenant Scope

Semua evidence dikunci ke `company_id` aktif.

## Integrasi

- **Payroll Run**: disburse batch membutuhkan evidence export reconciliation yang sudah diunduh.
- **BPJS Governance**: export laporan BPJS menggunakan pola yang sama.
- **Allowance Governance**: export compliance report juga dicatat sebagai evidence.
