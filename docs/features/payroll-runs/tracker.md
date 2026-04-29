# Payroll Runs Tracker

## Snapshot Status

- Tanggal: 2026-04-29
- Status: ready for deployment + payroll workflow UX shell aktif + hosted mock disburse enforced
- Ringkasan: runtime actual payroll inti tetap aktif end-to-end untuk surface yang dipublikasikan. Fondasi runtime policy cutoff/payday tenant-level tetap aktif (settings API, policy snapshot, scheduler tenant-aware, UI admin payroll run, dan payday holiday strategy tenant-level), enforcement cutoff-scoped untuk perhitungan draft monthly sudah berjalan, guard payday server-side untuk disburse monthly sudah aktif, regression matrix inti cutoff/payday sudah terkunci, kontrak operasional exception kini sudah dibakukan sebagai hard-block murni tanpa override inline, halaman `/payroll-run` tetap memakai workflow shell terarah, dan flow pembayaran payroll run sekarang wajib melewati hosted mock checkout + server-side approval token sebelum disburse diproses.

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
- **H4 (2026-04-26)** Dokumen desain enhancement `monthly-payroll-cutoff-and-payday-policy.md` diperluas untuk production-readiness: kini mencakup lifecycle operasional pre/post-cutoff, kebutuhan tanggal acuan per sumber data, UX anomaly + guardrail, dampak reporting/audit, dan persyaratan minimum sebelum fitur dinyatakan layak produksi.
- **H4 implementation (2026-04-26)** Runtime tenant-level monthly payroll settings kini tersedia di `/v1/hcm/payroll/settings` dengan UI admin pada halaman payroll run untuk `paydayDay`, `cutoffOffsetDays`, `payrollTimezone`, dan `disburseBeforePaydayAllowed`.
- **H4 implementation (2026-04-26)** Draft run `monthly` sekarang menyimpan `meta.policySnapshot` dan mengekspos `policySnapshot` ke payload period detail + payroll run detail/history agar audit payday/cutoff tetap stabil walau setting tenant berubah setelah draft dibuat.
- **H4 implementation (2026-04-26)** Scheduler refresh draft bulanan sekarang tenant-aware dan cutoff-aware: open period setelah `resolvedCutoffDate` tidak lagi direfresh otomatis, sementara period sebelum cutoff tetap direbuild harian.
- **H4 validation (2026-04-26)** Validasi fokus lulus: migration `2026_04_26_150000_add_meta_to_hcm_payroll_runs_table`, PHPUnit `HcmPayrollSettingsApiTest`, `HcmPayrollPeriodApiTest`, `RefreshOpenPayrollDraftsServiceTest`, `ConsoleScheduleRegistrationTest` (28 tests / 198 assertions), Vitest `tests/ui/payroll-run.wiring.test.js` (7 tests), build Vite, dan `scripts/check-api-docs-sync.sh` = OK.
- **H4 blocker close (2026-04-26)** BLOCKER-CP-01 ditutup: engine draft monthly kini memakai `policySnapshot.draftDataAsOfDate` (default resolved cutoff) sebagai batas data variabel untuk assignment eligibility, overtime aggregation, dan leave/holiday event adjustments. Evidence: PHPUnit `PayrollOvertimeRuleIntegrationTest`, `HcmPayrollPeriodApiTest`, `PayrollLeaveHolidayIntegrationTest` lulus (25 tests / 163 assertions), termasuk regression test `payroll_overtime_excludes_entries_after_cutoff_snapshot`.
- **H4 blocker close (2026-04-26)** BLOCKER-CP-02 ditutup: endpoint `POST /v1/hcm/payroll-runs/{id}/disburse` sekarang menolak disburse `monthly` sebelum `resolvedPaydayDate` jika `disburseBeforePaydayAllowed=false`, memakai `policySnapshot` run dan fallback ke settings tenant untuk run lama. Evidence: PHPUnit `HcmPayrollRunApiTest` lulus (33 tests / 359 assertions), termasuk regresi `test_admin_cannot_disburse_monthly_run_before_payday_when_policy_blocks_early_payment` dan `test_admin_can_disburse_before_payday_when_policy_allows_early_payment`.
- **H4 blocker close (2026-04-26)** BLOCKER-CP-03 ditutup: regression matrix cutoff/payday kini mencakup short month, leap year, post-cutoff exclusion, pre/post-cutoff UI state, dan before-payday policy reject. Evidence fokus: PHPUnit `HcmPayrollPeriodApiTest` lulus (21 tests / 164 assertions), Vitest `tests/ui/payroll-run.wiring.test.js` lulus (9 tests), ditambah regresi existing `PayrollOvertimeRuleIntegrationTest` dan `HcmPayrollRunApiTest` yang tetap hijau pada local gate.
- **H4 blocker close (2026-04-26)** BLOCKER-CP-04 ditutup: kontrak operasional exception diselaraskan dengan runtime MVP yang aktif. Keputusan final: hard-block murni tanpa override inline; exception hanya lewat perubahan policy tenant yang disetujui lalu rebuild snapshot run (`recalculate` untuk draft, `void + calculate draft ulang` untuk run finalized yang belum paid). Evidence close: policy doc, feature README, tracker, API docs/OpenAPI, dan wording UI payroll-run sudah sinkron; local gate tetap hijau setelah sinkronisasi.

## Gap Aktif

1. **GAP-OPS-04 (CLOSED, 2026-04-26)** Regression test khusus backlog pasca-cutoff dan auto-migrasi data ke periode berikutnya kini tersedia sebagai suite terpisah backend + UI.
2. Audit append-only terpisah di luar payload runtime masih enhancement governance, bukan blocker deploy.
4. Post-payroll adjustment/reversal lintas integrasi eksternal belum menjadi scope runtime aktif dan perlu keputusan bisnis bila nanti dibutuhkan.
5. Sinkronisasi narasi monthly vs THR vs PKWT compensation perlu dijaga saat modul payroll berkembang.
6. UI admin untuk toggle `payroll.leave_integration_enabled` per-tenant belum tersedia; saat ini aktivasi dilakukan via `company_settings` langsung atau env global. Surface UI diposisikan sebagai enhancement setelah pilot tenant.
7. Audit append-only inline untuk reason/approver exception masih enhancement masa depan bila tenant membutuhkan override yang tersimpan di sistem.

## Update Gap Lanjutan Terbaru

- **GAP-OPS-02 CLOSED (2026-04-26)**: guardrail post-cutoff review-only kini aktif di UI payroll-run.
- Implementasi: export reconciliation untuk payment otomatis nonaktif pada mode review-only; aksi buka modal disburse / submit disburse diblok dengan hint operasional yang jelas.
- Evidence: `frontend/resources/ts/payroll-run.ts` + Vitest `backend/tests/ui/payroll-run.wiring.test.js` menambah test `enforces post-cutoff review-only guardrail on export and disburse actions` (suite payroll-run wiring kini 10 tests, seluruhnya lulus).
- **GAP-OPS-01 CLOSED (2026-04-26)**: late-arrival buffer dan auto-migrasi periode berikutnya kini aktif end-to-end.
- Implementasi close:
	- `PayrollDraftBuilder` tetap menangkap backlog post-cutoff pada `run.meta.lateArrivalBuffer` (overtime + assignment effective post-cutoff).
	- Disburse monthly saat seluruh user eligible sudah dibayar kini memicu auto-migration engine (`PayrollLateArrivalMigrationService`) untuk queue+migrate backlog ke periode payroll berikutnya.
	- Draft periode berikutnya otomatis direbuild dan menyertakan carryover overtime dari source run yang dimigrasi.
- Kontrak API:
	- response disburse kini dapat memuat `lateArrivalMigration` (`targetPeriodYear`, `targetPeriodMonth`, `targetRunId`) untuk observabilitas migrasi.
	- `lateArrivalBuffer.migration` pada source run menyimpan status `queued`/`migrated`, target period, dan timestamp.
- Evidence close:
	- PHPUnit `HcmPayrollRunApiTest::test_paid_monthly_run_auto_migrates_late_arrival_buffer_into_next_period_draft` pass (21 assertions).
	- Regresi suite `HcmPayrollRunApiTest` pass (34 tests / 380 assertions) dan `HcmPayrollPeriodApiTest` pass (22 tests / 182 assertions).
- **GAP-OPS-03 CLOSED (2026-04-26)**: payday holiday strategy tenant-level kini aktif sebagai kontrak runtime payroll monthly.
- Implementasi close:
	- key tenant setting `payroll.monthly.payday_holiday_strategy` aktif di API `/v1/hcm/payroll/settings` + UI payroll-run;
	- snapshot monthly kini menyertakan `paydayHolidayStrategy` dan resolver runtime menghormati strategy `previous_working_day` / `next_working_day` / `exact_calendar_day` terhadap weekend + kalender libur;
	- preview policy pada UI payroll-run kini menampilkan resolved payday/cutoff sesuai strategy yang dipilih.
- Evidence close:
	- PHPUnit `HcmPayrollSettingsApiTest` + `HcmPayrollPeriodApiTest` pass (28 tests / 242 assertions), termasuk regresi weekend + holiday calendar strategy;
	- Vitest `tests/ui/payroll-run.wiring.test.js` pass (10 tests);
	- API docs + OpenAPI sinkron terhadap field `paydayHolidayStrategy`.
- **GAP-OPS-04 CLOSED (2026-04-26)**: regression coverage khusus backlog pasca-cutoff + auto-migrasi kini dipisahkan agar audit evidence tidak bercampur dengan suite umum.
- Implementasi close:
	- backend menambah suite dedicated `PayrollLateArrivalMigrationRegressionTest` untuk kontrak buffer post-cutoff, auto-migrasi saat disburse monthly, carryover ke draft periode berikutnya, dan visibilitas metadata migrasi pada detail/history;
	- UI payroll-run menampilkan ringkasan target periode migrasi (`lateArrivalMigration`) pada toast sukses disburse;
	- Vitest menambah suite dedicated `payroll-run-late-arrival.wiring.test.js` untuk memastikan payload migrasi tetap terlihat di surface operator.
- Evidence close:
	- PHPUnit `tests/Feature/PayrollLateArrivalMigrationRegressionTest.php` pass;
	- Vitest `tests/ui/payroll-run-late-arrival.wiring.test.js` pass;
	- local gate + docs sync guard dijalankan ulang pada closure GAP-OPS-04.
- **UX shell update (2026-04-29)**: surface `/payroll-run` kini memisahkan workflow menjadi stepper operasional, checklist readiness, primary action kontekstual, dan panel aksi lanjutan agar alur Calculate Draft → review → Export Reconciliation → Pay via Gateway lebih terbaca tetapi tetap selaras dengan template Bootstrap aktif.
- Evidence update:
	- Blade shell: `backend/resources/views/payroll-run.blade.php`.
	- Runtime renderer: `frontend/resources/ts/payroll-run.ts`.
	- Vitest `backend/tests/ui/payroll-run.wiring.test.js` pass dan menambah coverage untuk stage workflow + checklist readiness.
- **UX polish modal gateway (2026-04-29)**: modal `Pay via Gateway` dirapikan agar ringkasan batch dan rincian komponen per karyawan lebih mudah dibaca (hierarki heading, status badge, grouping penambah/pengurang, dan spacing list).
- Evidence update:
	- Modal shell: `backend/resources/views/payroll-run.blade.php` (`#payroll_gateway_modal`, dialog `modal-xl`, ringkasan batch + rincian karyawan).
	- Runtime renderer list: `frontend/resources/ts/payroll-run.ts` (`populateGatewayModal`).
	- Validasi: Vitest `backend/tests/ui/payroll-run.wiring.test.js` pass (11 tests) + `npm run build` pass.
- **Payment flow enforcement (2026-04-29)**: payroll disburse sekarang wajib lewat hosted mock payment terlebih dahulu (`mock-hosted-checkout` + `confirm`), lalu endpoint disburse menerima `mockApprovalToken` yang divalidasi server-side.
- Evidence update:
	- Controller: `backend/app/Http/Controllers/Api/HcmPayrollRunController.php`.
	- Route: `backend/routes/api.php`.
	- Frontend flow: `frontend/resources/ts/payroll-run.ts` + `backend/public/mock-hosted-payment.html`.
	- Kontrak API: `docs/api/hcm-payroll-api.md` + `docs/api/openapi.yaml`.
- **Payslip audience fix (2026-04-29)**: global super admin tidak lagi otomatis di-redirect dari `/payslip` ke `/payslip-report`, sehingga tetap bisa akses self-payslip pada tenant aktif.
- Evidence update:
	- Frontend audience guard: `frontend/resources/js/payslip-data.js`.

## Status Revisi Gap CP-01..CP-04

- Keputusan: **tidak perlu revisi substansi** untuk CP-01..CP-04 karena evidence runtime + test untuk empat blocker MVP tetap valid.
- Catatan: keputusan baru point 4-7 di policy doc menambah gap operasional lanjutan (GAP-OPS-01 s.d. GAP-OPS-04), bukan membuka ulang blocker MVP yang sudah closed.

## MVP Blocker Register (Cutoff/Payday)

Bagian ini menandai gap yang wajib ditutup sebelum enhancement cutoff/payday boleh dianggap production-complete.

1. **BLOCKER-CP-01 — Cutoff-scoped payroll line-item enforcement**
	- Status: CLOSED (2026-04-26)
	- Dampak: variabel payroll post-cutoff tidak lagi otomatis ikut periode berjalan pada source yang sudah di-enforce.
	- Evidence close: `PayrollOvertimeRuleIntegrationTest::test_payroll_overtime_excludes_entries_after_cutoff_snapshot`, plus `HcmPayrollPeriodApiTest` dan `PayrollLeaveHolidayIntegrationTest` pass (25 tests / 163 assertions).

2. **BLOCKER-CP-02 — Disburse payday hard guard**
	- Status: CLOSED (2026-04-26)
	- Dampak: pembayaran monthly sebelum payday sekarang ditolak server-side saat tenant melarang early disburse.
	- Evidence close: `HcmPayrollRunApiTest` pass (33 tests / 359 assertions), termasuk regression `test_admin_cannot_disburse_monthly_run_before_payday_when_policy_blocks_early_payment` dan `test_admin_can_disburse_before_payday_when_policy_allows_early_payment`.

3. **BLOCKER-CP-03 — Regression matrix coverage**
	- Status: CLOSED (2026-04-26)
	- Dampak: baseline edge case cutoff/payday utama sekarang sudah terproteksi oleh suite backend + UI.
	- Evidence close: `HcmPayrollPeriodApiTest` pass (21 tests / 164 assertions) untuk short month + leap year snapshot, `tests/ui/payroll-run.wiring.test.js` pass (9 tests) untuk pre/post-cutoff preview dan before-payday error toast, plus `PayrollOvertimeRuleIntegrationTest` dan `HcmPayrollRunApiTest` tetap hijau.

4. **BLOCKER-CP-04 — Operational exception contract**
	- Status: CLOSED (2026-04-26)
	- Dampak: interpretasi HR, payroll admin, dan finance sekarang konsisten dengan runtime cutoff/payday yang aktif.
	- Evidence close: keputusan final hard-block murni tanpa override inline sudah disinkronkan di policy doc, feature README, API docs/OpenAPI, tracker, dan wording UI payroll-run; local gate tetap hijau.

## Urutan Eksekusi Yang Direkomendasikan

1. Tutup BLOCKER-CP-01 (engine cutoff-scoped) agar hasil payroll sesuai policy perioding.
2. MVP blocker cutoff/payday selesai; tindak lanjut berikutnya bersifat enhancement governance, bukan blocker deploy.

## Keputusan Saat Ini

- Anggap temuan audit yang menyasar identifier/send-slips/disburse dan readiness runtime payroll run sudah tertutup.
- Integrasi cuti/libur (H3) dirilis opt-in: tenant existing tidak otomatis terpengaruh; aktivasi hanya saat master salary components + kalender libur + unpaid leave policy sudah siap.
- Perlakukan sisa pekerjaan sebagai hardening governance/compliance lanjutan, bukan blocker deploy untuk menu payroll yang aktif.
- Gunakan implementasi cutoff/payday saat ini sebagai fondasi aman: settings tenant + snapshot audit + scheduler-aware + disburse payday guard server-side + regression matrix inti + kontrak operasional final sudah aktif.
- Enhancement cutoff/payday untuk scope MVP kini dapat diperlakukan sebagai production-complete; follow-up berikutnya adalah enhancement governance lanjutan bila tenant meminta approval trail inline atau adjustment workflow tambahan.