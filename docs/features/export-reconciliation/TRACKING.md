# Export Reconciliation - Tracking Pengerjaan

Dokumen ini dipakai untuk memantau progres implementasi lintas tim.

Status legend:

- TODO: belum mulai
- IN_PROGRESS: sedang dikerjakan
- BLOCKED: terhambat dependensi
- DONE: selesai dan tervalidasi

## Status Saat Ini

- Current phase: M6-M7 (export UI + indicator selesai dan build, gate aktif; fokus payment flow testing)
- Last update: 2026-04-15
- Owner aktif: Product + Backend + Frontend
- Blocker aktif: belum ada

## Yang Belum Dilakukan (Clear Pending)

### Prioritas 1 - Product decision (blocking)

- [ ] Final policy TTL evidence export belum diputuskan (default berjalan, keputusan final belum dikunci).
- [ ] Keputusan strict checksum mode (ON/OFF per endpoint) belum diputuskan.

### Prioritas 2 - Frontend (SELESAI)

- [x] Tombol export reconciliation tersedia di payroll-run, payroll-thr, PKWT (build 2026-04-15).
- [x] Indicator evidence valid/invalid + timestamp tampil di panel action setiap flow.

### Prioritas 3 - QA & Evidence

- [ ] Test matrix per fitur/action belum ditulis lengkap dan dieksekusi.
- [ ] Manual UI E2E per role (admin vs non-admin) belum dijalankan dan belum ada bukti pass/fail terstruktur.
- [ ] Evidencing tenant boundary belum terdokumentasi lengkap (screenshot/log per skenario).

### Prioritas 4 - Closure

- [ ] FE-BE contract validation untuk normalisasi filter payload belum ditutup.
- [ ] Internal release note final belum dibuat.

## Definition Of Done (supaya status tidak ambigu)

Item dianggap DONE jika memenuhi semua syarat berikut:

1. Implementasi kode sudah masuk.
2. Ada verifikasi (test/hasil run/manual evidence) yang bisa dirujuk.
3. Dokumen terdampak sudah sinkron.
4. Jika menyentuh role/flow HCM: role gate -> UIUX gate -> manual UI E2E sudah dieksekusi/tercatat.

Jika salah satu belum terpenuhi, status tetap `IN_PROGRESS` atau `TODO`.

## Next Action Order (fokus payment flow testing)

1. Payment flow UI E2E: Admin trigger export + payment disburse/pay/verify pada payroll/THR/PKWT.
2. Non-admin guard test: Verify blocked at auth before gate runs.
3. FE-BE contract validation: Normalize filterPayload serialization.
4. Lock keputusan Product: TTL + strict checksum mode.
5. Finalisasi release note + deployment procedure.

## Board Eksekusi Harian (Working Plan)

| Date | Fokus Harian | PIC | Output Harian | Status |
|---|---|---|---|---|
| 2026-04-15 | Lock scope endpoint dan error code domain | Product + Backend | Dokumen feature pack + API contract + tracking baseline | DONE |
| 2026-04-16 | Finalisasi kebijakan TTL + strict checksum mode | Product + Backend | Keputusan policy final + update dokumen implementasi | TODO |
| 2026-04-17 | Migration evidence + model + repository layer | Backend | Skema `export_reconciliation_evidences` + unit test gate baseline | DONE |
| 2026-04-18 | Endpoint export MVP payroll run + invoice/payment | Backend | Endpoint export working (CSV) + auth/tenant scope | DONE |
| 2026-04-19 | Integrasi gate ke action controller prioritas | Backend | Gate aktif pada finalize/disburse/mark-paid/verify + THR/PKWT post flow | DONE |
| 2026-04-20 | Wiring UI indicator + warning + export CTA + build aset | Frontend | Mapping error `EXPORT_RECON_*` + persistent warning banner + export CTA + evidence indicator + npm build done | DONE |
| 2026-04-21 | Payment flow UI E2E: admin export + disburse/pay | QA | Test payroll disburse + THR pay + PKWT pay after export reconciliation, log pass/fail di E2E-TESTING.md | IN_PROGRESS |
| 2026-04-21 | Feature tests backend + kontrak FE-BE | Backend + QA | Regression auth-before-gate (payroll/invoice/payment + THR/PKWT) sudah masuk; kontrak FE-BE tersisa + bukti run wajib di `docs/features/export-reconciliation/TEST-LOG.md` | IN_PROGRESS |
| 2026-04-22 | Manual UI E2E per role + tenant boundary | QA | Laporan E2E admin vs non-admin + evidence screenshot + checklist pass/fail di `docs/features/export-reconciliation/E2E-TESTING.md` | TODO |
| 2026-04-23 | OpenAPI/docs/security sync + release note internal | Backend + QA | OpenAPI + security inventory + role matrix sudah sinkron; release note final wajib di `docs/features/export-reconciliation/RELEASE-NOTES.md` | IN_PROGRESS |

## Checklist Eksekusi (PIC + Bukti Wajib)

| ID | Task | PIC | Status | Bukti wajib (file/artefak) | Target tanggal |
|---|---|---|---|---|---|
| EX-01 | Lock policy TTL evidence export | Product + Backend | TODO | Keputusan final TTL ditulis di `docs/features/export-reconciliation/IMPLEMENTATION.md` bagian policy + ringkasan di change log | 2026-04-16 |
| EX-02 | Lock strict checksum mode per endpoint | Product + Backend | TODO | Matriks ON/OFF strict mode per endpoint di `docs/features/export-reconciliation/IMPLEMENTATION.md` + update `docs/api/openapi.yaml` jika behavior/error berubah | 2026-04-16 |
| EX-03 | Tambah export CTA pada payroll-run | Frontend | DONE | Commit: feat(export-reconciliation), payroll-run.blade.php line 76, build ✓ | 2026-04-15 |
| EX-04 | Tambah export CTA pada payroll-thr | Frontend | DONE | Commit: feat(export-reconciliation), payroll-thr.blade.php line 137, build ✓ | 2026-04-15 |
| EX-05 | Tambah export CTA pada PKWT compensation | Frontend | DONE | Commit: feat(export-reconciliation), payroll-pkwt-compensation.blade.php line 69, build ✓ | 2026-04-15 |
| EX-06 | Indicator evidence pada payroll-run | Frontend | DONE | payroll-run.ts showEvidenceIndicator(), public/build/js/payroll-run.js ✓ | 2026-04-15 |
| EX-07 | Indicator evidence pada THR | Frontend | DONE | thr-payroll-batch.ts showThrEvidenceIndicator(), public/build/js/thr-payroll-batch.js ✓ | 2026-04-15 |
| EX-08 | Indicator evidence pada PKWT | Frontend | DONE | pkwt-compensation-data.js showPkwtEvidenceIndicator(), public/build/js/pkwt-compensation-data.js ✓ | 2026-04-15 |
| EX-09 | Tutup FE-BE contract test normalisasi filter | Backend + QA | TODO | Hasil test dan daftar kasus uji di `docs/features/export-reconciliation/TEST-LOG.md` | 2026-04-21 |
| EX-10 | Manual UI E2E role gate (admin vs non-admin) | QA | TODO | Log pass/fail per skenario di `docs/features/export-reconciliation/E2E-TESTING.md` + screenshot | 2026-04-22 |
| EX-11 | Manual UI E2E tenant boundary | QA | TODO | Log pass/fail tenant isolation di `docs/features/export-reconciliation/E2E-TESTING.md` + screenshot/error payload | 2026-04-22 |
| EX-12 | Finalisasi release note internal | Backend + QA | TODO | Dokumen `docs/features/export-reconciliation/RELEASE-NOTES.md` (ringkasan perubahan, risiko, rollback) | 2026-04-23 |

Catatan eksekusi:

- Setiap task EX-* tidak boleh dipindah ke DONE tanpa bukti pada PR/commit atau dokumen evidence terkait.
- Jika task butuh keputusan lintas tim, status minimal `IN_PROGRESS` wajib menuliskan owner aktif dan blocker saat itu.

## Milestone Utama

| Milestone | Target | Status | Owner | Catatan |
|---|---|---|---|---|
| M1 - Finalisasi requirement + scope action berisiko | 2026-04-16 | IN_PROGRESS | Product + Backend | Scope awal payroll + invoice/payment sudah terdefinisi |
| M2 - Desain data model evidence + migration | 2026-04-17 | DONE | Backend | Migration + model + gate/export service skeleton sudah masuk |
| M3 - Endpoint export MVP (payroll + billing) | 2026-04-19 | DONE | Backend | Endpoint `POST/GET/download` evidence aktif |
| M4 - Gate validation di action controller | 2026-04-20 | DONE | Backend | Gate aktif via feature flag per endpoint prioritas + THR/PKWT |
| M5 - UI indicator + warning sebelum action | 2026-04-21 | DONE | Frontend | Error mapping + persistent warning + export CTA + evidence indicator + npm build selesai (2026-04-15) |
| M6 - Feature test + regression test | 2026-04-22 | IN_PROGRESS | QA + Backend | Regression backend sudah bertambah; payment flow testing lanjut, FE-BE contract + rollout notes |
| M7 - Manual UI E2E per role + payment flow | 2026-04-23 | IN_PROGRESS | QA | Admin payment (payroll disburse + THR pay + PKWT pay) + non-admin guard + tenant boundary |
| M8 - OpenAPI + docs sync + rollout notes | 2026-04-24 | IN_PROGRESS | Backend + QA | OpenAPI + security/docs + role matrix sinkron; payment flow evidence + rollout notes final |

## Breakdown Teknis Per Endpoint Prioritas

| Endpoint Existing | Action Risk | Export Scope | Gate Rule | Backend Task | Frontend Task | Test Minimum |
|---|---|---|---|---|---|---|
| `POST /v1/hcm/payroll-runs/{id}/finalize` | finalize monthly run | payroll_run:runId | wajib evidence valid + scope match | hook gate service sebelum finalize | warning jika evidence belum ada | required + mismatch |
| `POST /v1/hcm/payroll-runs/{id}/disburse` | disburse payroll | payroll_run:runId | wajib evidence valid + belum stale | gate + validasi userIds/filter | indicator status evidence di panel gateway | required + stale |
| `POST /v1/hcm/payroll/thr-batch/disburse` | disburse THR | thr_batch:batchId | wajib evidence batch terbaru | gate di controller THR batch | warning sebelum submit disburse | required + tenant boundary |
| `POST /v1/hcm/payroll/thr-batch/post-payroll` | post THR to payroll | thr_batch:batchId | evidence harus sesuai batch/payment period | gate + context period check | blok tombol jika invalid | required + scope mismatch |
| `POST /v1/hcm/payroll/pkwt-compensations/post-payroll` | post PKWT payroll | pkwt_compensation:period | evidence period sesuai payload | gate + context period check | warning sebelum post payroll | required + stale |
| `PUT /v1/saas/invoices/{invoice}/mark-paid` | mark invoice paid | invoice:invoiceId | evidence invoice wajib ada | gate sebelum mark paid | badge evidence pada detail invoice | required + forbidden |
| `PUT /v1/saas/payments/{payment}/verify` | verify payment | payment:paymentId | evidence payment wajib ada | gate sebelum verify | warning sebelum verify | required + scope mismatch |

---

## Work Breakdown Structure

### A. Product & Analysis

- [x] Definisikan problem statement dan objective.
- [x] Definisikan fitur target dan action berisiko.
- [ ] Tetapkan policy TTL evidence export.
- [ ] Tetapkan strict mode checksum aktif/tidak.

### B. Backend

- [x] Migration table `export_reconciliation_evidences`.
- [x] Service `ReconciliationExportService`.
- [x] Service `ReconciliationGateService`.
- [x] Endpoint trigger export (`POST /v1/reconciliation/exports`).
- [x] Endpoint list evidence (`GET /v1/reconciliation/exports`).
- [x] Endpoint download evidence (`GET /v1/reconciliation/exports/{id}/download`).
- [x] Integrasi gate ke payroll actions prioritas (monthly finalize/disburse + THR/PKWT flow).
- [x] Integrasi gate ke invoice/payment actions (mark-paid/verify).
- [x] Unit test baseline gate (`ReconciliationGateServiceTest`).
- [x] Feature tests baseline endpoint + enforcement prioritas termasuk THR/PKWT.

### C. Frontend

- [ ] Tombol export reconciliation pada halaman target prioritas.
- [ ] Indicator evidence valid/invalid per scope.
- [x] Warning state sebelum action berisiko (non-native confirm).
- [x] Error handling code-level (`EXPORT_RECON_*`).

### D. QA

- [ ] Test matrix per fitur/action.
- [ ] Uji mismatch filter/scope.
- [ ] Uji stale data scenario.
- [ ] Uji role permission dan tenant boundary.

### E. Documentation & Security

- [x] Draft README/Implementation/API/Tracking fitur.
- [x] Update `docs/api/openapi.yaml` saat endpoint aktif.
- [x] Update docs feature terkait payroll & billing.
- [x] Tambahkan catatan security inventory jika surface berubah.

---

## Current Risks

1. Definisi checksum strict mode belum disepakati.
2. Potensi beban query tinggi untuk export dataset besar.
3. Risiko UX friction jika gate terlalu ketat tanpa guidance.
4. Potensi mismatch filter UI vs payload backend jika tidak distandarisasi.

## Mitigasi

1. Lock contract filter payload sejak fase MVP.
2. Batasi ukuran export + pagination/streaming.
3. Tambahkan pesan error actionable di UI.
4. Tambahkan test kontrak FE-BE untuk filter normalization.

---

## Change Log

- 2026-04-15: Dokumen tracking pertama dibuat.
- 2026-04-15: Milestone M1 ditandai IN_PROGRESS.
- 2026-04-15: Ditambahkan board eksekusi harian + breakdown teknis per endpoint prioritas.
- 2026-04-15: Backend baseline selesai (migration evidence + model + service skeleton + unit test gate).
- 2026-04-15: Runtime API reconciliation aktif (`POST/GET/download`) + gate enforcement pada payroll finalize/disburse, invoice mark-paid, payment verify.
- 2026-04-15: Ditambahkan feature test baseline untuk endpoint reconciliation dan enforcement flow prioritas.
- 2026-04-15: Gate reconciliation diperluas ke `POST /v1/hcm/payroll/thr-batch/disburse`, `POST /v1/hcm/payroll/thr-batch/post-payroll`, dan `POST /v1/hcm/payroll/pkwt-compensations/post-payroll`.
- 2026-04-15: Feature test THR/PKWT ditambah untuk validasi `EXPORT_RECON_REQUIRED` dan flow lanjut setelah evidence tersedia.
- 2026-04-15: Sinkronisasi docs security inventory + matriks role aktif; ditegaskan export reconciliation adalah kontrol admin/operator (customer non-admin tidak diwajibkan export manual).
- 2026-04-15: Regression test ditambah untuk memastikan non-admin ditolak lebih dulu oleh auth (403) sebelum gate reconciliation pada payroll/invoice/payment + THR/PKWT.
- 2026-04-15: Frontend payroll-run, payroll-thr, dan PKWT compensation menambahkan mapping pesan `EXPORT_RECON_*` yang actionable.
- 2026-04-15: Ditambahkan persistent warning banner reconciliation di halaman payroll-run/THR/PKWT (bukan hanya toast) dan clear state saat action berhasil.
- 2026-04-15: Build aset frontend ulang berhasil setelah perubahan UI hint reconciliation.
