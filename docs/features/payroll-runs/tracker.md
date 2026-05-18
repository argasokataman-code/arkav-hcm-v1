# Payroll Runs Tracker

## Snapshot Status

- Tanggal: 2026-05-18
- Status: runtime hardened, monthly reporting aktif, release evidence masih parsial
- Ringkasan: runtime actual payroll inti aktif untuk surface yang dipublikasikan; void-before-paid, payslip web/PDF/email, overtime roll-up, deduction engine, history payroll, Monthly Report admin, dan export reconciliation payment-ready lintas monthly/THR/PKWT sudah punya evidence implementasi. Closure release 100% tetap menunggu bukti manual E2E export/payment per role.

## Evidence Terbaru

- Wiring UI payroll run tervalidasi di `backend/tests/ui/payroll-run.wiring.test.js`.
- Wiring UI history payroll void filter/badge tervalidasi di `backend/tests/ui/payroll-run-history.wiring.test.js`.
- Backend payroll run flow tervalidasi di `backend/tests/Feature/HcmPayrollRunApiTest.php`.
- API payroll/send-slips dan related payroll flow tervalidasi di `backend/tests/Feature/HcmPayrollApiTest.php`.
- Gate export reconciliation terkait payroll run tervalidasi di `backend/tests/Feature/ReconciliationExportApiTest.php`.
- Monthly Report admin (`/monthly-report`) dan export gabungan monthly/THR/PKWT tervalidasi di `backend/tests/Feature/HcmPayrollApiTest.php` + `backend/tests/Feature/WebHcmRouteGuardTest.php`.
- Export reconciliation payment-ready seragam untuk `payroll_run`, `thr_batch`, dan `pkwt_compensation` tervalidasi di `backend/tests/Feature/ReconciliationExportApiTest.php`.
- README dan UI payroll kini menegaskan bahwa perubahan setup payroll harus memakai void dulu bila run sudah finalized tetapi belum paid.
- Metadata `voidedAt` / `voidedBy*` sekarang tersimpan di `hcm_payroll_runs` dan diekspos ke `auditTrail` history/detail payroll run.
- `/payslip` web aktif memakai `my-slip`, `my-slip-latest-period`, dan `my-slip-pdf` pada runtime employee.
- Overtime approved dan deduction engine BPJS/PPh21 TER sudah tervalidasi lewat `backend/tests/Feature/HcmPayrollApiTest.php`.
- Summary overtime eksplisit untuk `my-slip`, `admin-slips`, `monthly-report`, export CSV Monthly Report, slip PDF, dan email payslip tervalidasi di `backend/tests/Feature/HcmPayrollApiTest.php` + build Vite `npm run build`.
- **H3 (2026-04-23)** Integrasi cuti tanpa gaji + kerja hari libur aktif via feature flag `payroll.leave_integration_enabled`. Default OFF (tidak mengubah tenant existing). Evidence: `backend/tests/Feature/PayrollLeaveHolidayIntegrationTest.php` (3 tests, 13 assertions — flag OFF no-op, flag ON global emit deduction + addition, flag per-tenant via `company_settings`). Full regresi payroll masih hijau: `HcmPayrollPeriodApiTest`, `HcmPayrollRunApiTest`, `HcmPayrollItemApiTest`, `HcmPayrollThrApiTest`, `HcmPayrollPkwtApiTest` → 88 tests / 2166 assertions pass.
- **H4 (2026-05-17)** Hardening permission mutasi payroll: `calculate-draft` kini butuh `payroll.run`; THR setup/generate/post/send butuh `payroll.thr.manage`; THR disburse butuh `payroll.disburse`; PKWT post payroll butuh `payroll.pkwt.manage`; payroll item assignment mutate butuh `payroll.manage`. Validasi sesi ini: `php -l` lulus untuk controller payroll terkait + `backend/tests/TestCase.php`, serta API doc/OpenAPI payroll disinkronkan.

## Gap Aktif

1. Manual UI E2E export/payment per role untuk payroll run, THR, dan PKWT masih tercatat `IN_PROGRESS` di feature export reconciliation; selama evidence ini belum lengkap, status release tidak boleh disebut 100% closed.
2. FE-BE contract validation untuk normalisasi payload export reconciliation masih pending di tracker feature terkait.
3. Audit append-only terpisah di luar payload runtime masih enhancement governance, bukan blocker runtime.
4. Post-payroll adjustment/reversal lintas integrasi eksternal belum menjadi scope runtime aktif dan perlu keputusan bisnis bila nanti dibutuhkan.
5. UI admin untuk toggle `payroll.leave_integration_enabled` per-tenant belum tersedia; saat ini aktivasi dilakukan via `company_settings` langsung atau env global. Surface UI diposisikan sebagai enhancement setelah pilot tenant.

## Keputusan Saat Ini

- Anggap temuan audit RBAC yang menyasar mutasi payroll run/THR/PKWT/item assignment sudah tertutup di runtime.
- Integrasi cuti/libur (H3) dirilis opt-in: tenant existing tidak otomatis terpengaruh; aktivasi hanya saat master salary components + kalender libur + unpaid leave policy sudah siap.
- Perlakukan sisa pekerjaan export reconciliation manual E2E dan contract evidence sebagai blocker evidence release, bukan blocker runtime coding.