# Export Reconciliation - Tracking Pengerjaan

Dokumen ini dipakai untuk memantau progres implementasi lintas tim.

Status legend:

- TODO: belum mulai
- IN_PROGRESS: sedang dikerjakan
- BLOCKED: terhambat dependensi
- DONE: selesai dan tervalidasi

## Status Saat Ini

- Current phase: M5-M6 (runtime gate selesai, regression test backend + UI warning/error handling aktif; lanjut export CTA/indicator + QA manual)
- Last update: 2026-04-15
- Owner aktif: Product + Backend + Frontend
- Blocker aktif: belum ada

## Yang Belum Dilakukan (Clear Pending)

### Prioritas 1 - Product decision (blocking)

- [ ] Final policy TTL evidence export belum diputuskan (default berjalan, keputusan final belum dikunci).
- [ ] Keputusan strict checksum mode (ON/OFF per endpoint) belum diputuskan.

### Prioritas 2 - Frontend (masih tersisa)

- [ ] Tombol export reconciliation belum tersedia end-to-end di halaman prioritas (payroll-run, payroll-thr, PKWT).
- [ ] Indicator evidence valid/invalid per scope belum tampil konsisten di panel action.

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

## Next Action Order (eksekusi realistis)

1. Lock keputusan Product: TTL + strict checksum mode.
2. Selesaikan UI admin: export CTA + evidence indicator pada payroll-run/THR/PKWT.
3. Jalankan manual UI E2E per role + kumpulkan evidence.
4. Tutup FE-BE contract test normalisasi filter.
5. Finalisasi release note internal.

## Board Eksekusi Harian (Working Plan)

| Date | Fokus Harian | PIC | Output Harian | Status |
|---|---|---|---|---|
| 2026-04-15 | Lock scope endpoint dan error code domain | Product + Backend | Dokumen feature pack + API contract + tracking baseline | DONE |
| 2026-04-16 | Finalisasi kebijakan TTL + strict checksum mode | Product + Backend | Keputusan policy final + update dokumen implementasi | TODO |
| 2026-04-17 | Migration evidence + model + repository layer | Backend | Skema `export_reconciliation_evidences` + unit test gate baseline | DONE |
| 2026-04-18 | Endpoint export MVP payroll run + invoice/payment | Backend | Endpoint export working (CSV) + auth/tenant scope | DONE |
| 2026-04-19 | Integrasi gate ke action controller prioritas | Backend | Gate aktif pada finalize/disburse/mark-paid/verify + THR/PKWT post flow | DONE |
| 2026-04-20 | Wiring UI indicator + warning + non-native confirm | Frontend | Mapping error `EXPORT_RECON_*` + persistent warning banner payroll/THR/PKWT (export CTA + badge evidence masih lanjut) | IN_PROGRESS |
| 2026-04-21 | Feature tests backend + kontrak FE-BE | Backend + QA | Regression auth-before-gate (payroll/invoice/payment + THR/PKWT) sudah masuk; kontrak FE-BE tersisa + bukti run wajib di `docs/features/export-reconciliation/TEST-LOG.md` | IN_PROGRESS |
| 2026-04-22 | Manual UI E2E per role + tenant boundary | QA | Laporan E2E admin vs non-admin + evidence screenshot + checklist pass/fail di `docs/features/export-reconciliation/E2E-TESTING.md` | TODO |
| 2026-04-23 | OpenAPI/docs/security sync + release note internal | Backend + QA | OpenAPI + security inventory + role matrix sudah sinkron; release note final wajib di `docs/features/export-reconciliation/RELEASE-NOTES.md` | IN_PROGRESS |

## Checklist Eksekusi (PIC + Bukti Wajib)

| ID | Task | PIC | Status | Bukti wajib (file/artefak) | Target tanggal |
|---|---|---|---|---|---|
| EX-01 | Lock policy TTL evidence export | Product + Backend | TODO | Keputusan final TTL ditulis di `docs/features/export-reconciliation/IMPLEMENTATION.md` bagian policy + ringkasan di change log | 2026-04-16 |
| EX-02 | Lock strict checksum mode per endpoint | Product + Backend | TODO | Matriks ON/OFF strict mode per endpoint di `docs/features/export-reconciliation/IMPLEMENTATION.md` + update `docs/api/openapi.yaml` jika behavior/error berubah | 2026-04-16 |
| EX-03 | Tambah export CTA pada payroll-run | Frontend | TODO | Screenshot UI + diff kode + bukti build aset | 2026-04-20 |
| EX-04 | Tambah export CTA pada payroll-thr | Frontend | TODO | Screenshot UI + diff kode + bukti build aset | 2026-04-20 |
| EX-05 | Tambah export CTA pada PKWT compensation | Frontend | TODO | Screenshot UI + diff kode + bukti build aset | 2026-04-20 |
| EX-06 | Indicator evidence valid/invalid pada payroll-run | Frontend | TODO | Screenshot state valid/invalid + mapping API response pada source | 2026-04-20 |
| EX-07 | Indicator evidence valid/invalid pada THR | Frontend | TODO | Screenshot state valid/invalid + mapping API response pada source | 2026-04-20 |
| EX-08 | Indicator evidence valid/invalid pada PKWT | Frontend | TODO | Screenshot state valid/invalid + mapping API response pada source | 2026-04-20 |
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
| M5 - UI indicator + warning sebelum action | 2026-04-21 | IN_PROGRESS | Frontend | Error mapping + persistent warning untuk payroll/THR/PKWT sudah aktif; tombol export + badge evidence belum full |
| M6 - Feature test + regression test | 2026-04-22 | IN_PROGRESS | QA + Backend | Regression backend (happy path + forbidden/auth-order + stale/mismatch) sudah bertambah; FE-BE contract + evidencing QA lanjut |
| M7 - Manual UI E2E per role | 2026-04-23 | TODO | QA | Admin vs non-admin guard |
| M8 - OpenAPI + docs sync + rollout notes | 2026-04-24 | IN_PROGRESS | Backend + QA | OpenAPI + security/docs + role matrix sinkron; rollout notes final belum |

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
