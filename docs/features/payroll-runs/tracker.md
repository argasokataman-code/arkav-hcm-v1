# Payroll Runs Tracker

## Snapshot Status

- Tanggal: 2026-04-23
- Status: ready for deployment
- Ringkasan: runtime actual payroll inti aktif end-to-end untuk surface yang dipublikasikan; void-before-paid, payslip web/PDF, overtime roll-up, deduction engine, dan history payroll sudah punya evidence implementasi. Audit H3 menutup celah integrasi cuti/libur sebagai fitur opt-in yang aman dipasang lintas tenant.

## Evidence Terbaru

- Wiring UI payroll run tervalidasi di `backend/tests/ui/payroll-run.wiring.test.js`.
- Wiring UI history payroll void filter/badge tervalidasi di `backend/tests/ui/payroll-run-history.wiring.test.js`.
- Backend payroll run flow tervalidasi di `backend/tests/Feature/HcmPayrollRunApiTest.php`.
- API payroll/send-slips dan related payroll flow tervalidasi di `backend/tests/Feature/HcmPayrollApiTest.php`.
- Gate export reconciliation terkait payroll run tervalidasi di `backend/tests/Feature/ReconciliationExportApiTest.php`.
- README dan UI payroll kini menegaskan bahwa perubahan setup payroll harus memakai void dulu bila run sudah finalized tetapi belum paid.
- Metadata `voidedAt` / `voidedBy*` sekarang tersimpan di `hcm_payroll_runs` dan diekspos ke `auditTrail` history/detail payroll run.
- `/payslip` web aktif memakai `my-slip`, `my-slip-latest-period`, dan `my-slip-pdf` pada runtime employee.
- Overtime approved dan deduction engine BPJS/PPh21 TER sudah tervalidasi lewat `backend/tests/Feature/HcmPayrollApiTest.php`.
- **H3 (2026-04-23)** Integrasi cuti tanpa gaji + kerja hari libur aktif via feature flag `payroll.leave_integration_enabled`. Default OFF (tidak mengubah tenant existing). Evidence: `backend/tests/Feature/PayrollLeaveHolidayIntegrationTest.php` (3 tests, 13 assertions — flag OFF no-op, flag ON global emit deduction + addition, flag per-tenant via `company_settings`). Full regresi payroll masih hijau: `HcmPayrollPeriodApiTest`, `HcmPayrollRunApiTest`, `HcmPayrollItemApiTest`, `HcmPayrollThrApiTest`, `HcmPayrollPkwtApiTest` → 88 tests / 2166 assertions pass.

## Gap Aktif

1. Audit append-only terpisah di luar payload runtime masih enhancement governance, bukan blocker deploy.
2. Post-payroll adjustment/reversal lintas integrasi eksternal belum menjadi scope runtime aktif dan perlu keputusan bisnis bila nanti dibutuhkan.
3. Sinkronisasi narasi monthly vs THR vs PKWT compensation perlu dijaga saat modul payroll berkembang.
4. UI admin untuk toggle `payroll.leave_integration_enabled` per-tenant belum tersedia; saat ini aktivasi dilakukan via `company_settings` langsung atau env global. Surface UI diposisikan sebagai enhancement setelah pilot tenant.

## Keputusan Saat Ini

- Anggap temuan audit yang menyasar identifier/send-slips/disburse dan readiness runtime payroll run sudah tertutup.
- Integrasi cuti/libur (H3) dirilis opt-in: tenant existing tidak otomatis terpengaruh; aktivasi hanya saat master salary components + kalender libur + unpaid leave policy sudah siap.
- Perlakukan sisa pekerjaan sebagai hardening governance/compliance lanjutan, bukan blocker deploy untuk menu payroll yang aktif.