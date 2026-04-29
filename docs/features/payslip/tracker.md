# Payslip Tracker

## Status Snapshot

- Status: READY FOR DEPLOYMENT
- Last reviewed: 2026-04-29
- Scope: halaman `/payslip`, fallback periode final terbaru, dan unduhan PDF self-service.

## Evidence Terbaru

- Runtime page aktif: `backend/resources/views/payslip.blade.php` + `frontend/resources/js/payslip-data.js`.
- Terminologi UI diperjelas agar tidak ambigu: `My Payslip` (self), `Payslip Report (All Employees)` (admin), dan `Payroll Run History`.
- Footer script memuat asset khusus saat route `payslip` aktif.
- Feature coverage ada di `backend/tests/Feature/HcmPayrollRunApiTest.php` untuk self-service payslip dan latest finalized period.
- API contract hidup di `docs/api/hcm-payroll-api.md` dan `docs/api/openapi.yaml`.

## Gap / Risiko Terbuka

- Belum ada log manual UI E2E khusus employee payslip per role di feature pack ini.
- Bila agregasi slip lintas purpose berubah, tracker ini harus diperbarui bersama evidence test terbaru.

## Catatan Update

- 2026-04-20: feature pack dibuat agar halaman `/payslip` tidak lagi tercecer di README payroll lain dan statusnya bisa dilacak sendiri.
- 2026-04-29: wording dan naming diselaraskan di sidebar, halaman payslip, halaman report, JS hint, dan README feature untuk menghilangkan ambigu antara self slip vs report admin.
