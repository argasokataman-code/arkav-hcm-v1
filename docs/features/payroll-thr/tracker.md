# Payroll THR Tracker

## Status Snapshot

- Status: READY FOR DEPLOYMENT
- Last reviewed: 2026-04-20
- Scope: yearly settings, THR batch generate/disburse/post-payroll, slip PDF, dan integrasi ke payslip.

## Evidence Terbaru

- Runtime page aktif: `backend/resources/views/payroll-thr.blade.php`.
- Asset route-specific aktif: `build/js/thr-payroll-batch.js`.
- Feature coverage ada di `backend/tests/Feature/HcmPayrollThrApiTest.php` untuk calculator, settings, batch lifecycle, self-slip, resignation/termination filter, dan reconciliation enforcement.
- Kontrak API aktif ada di `docs/api/hcm-payroll-api.md` dan `docs/api/openapi.yaml`.

## Gap / Risiko Terbuka

- Manual UI E2E admin untuk flow export + pay + post masih perlu evidence terpisah bila ingin closure QA formal.
- Jika kebijakan evidence export berubah, tracker ini harus ikut memperbarui dependency ke feature `export-reconciliation`.

## Catatan Update

- 2026-04-20: feature pack dibuat agar `/payroll-thr` punya owner doc sendiri, tidak lagi numpang deskripsi singkat di indeks fitur payroll.
