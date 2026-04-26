# Tax Governance & Taxonomy Tracker

## Snapshot Status

- Tanggal: 2026-05-03
- Status: phase 1 done, phase 2 done, phase 3 done, phase 4 in-progress
- Ringkasan: keputusan produk sudah final dan implementasi runtime phase 4 sudah dimulai (migration + model + controller + route + baseline tests) untuk lifecycle policy tenant UUID-only dengan guardrail RBAC, SoD, dan tenant boundary.

## Progress Phase (Live)

| Phase | Nama | Status | Catatan singkat |
|---|---|---|---|
| 1 | Baseline Dokumentasi dan Scope Lock | done | Baseline docs, decision lock, RBAC matrix detail sudah sinkron. |
| 2 | UI/UX Planning dan Role Journey | done | UI/UX plan disetujui sementara; siap jadi acuan implementasi FE/BE/QA. |
| 3 | API Contract dan Permission Mapping | done | Kontrak API UUID-only, endpoint-permission map, dan sinkronisasi OpenAPI phase 3 sudah dikunci. |
| 4 | Runtime Control Plane Foundation | in-progress | Baseline runtime sudah mulai di backend: persistence policy/events, endpoint lifecycle tenant, dan test awal SoD + tenant boundary. |
| 5 | Governance Dashboard Lintas Tenant | not-started | Menunggu projection model dan metric definition. |
| 6 | Negative Path Hardening | not-started | Menunggu wiring runtime + UX warning implementation. |
| 7 | Audit Evidence Pack | not-started | Menunggu domain event + reporting pipeline. |
| 8 | UUID Migration Execution | not-started | Menunggu object model final + migration plan rinci. |
| 9 | Platform Billing Tax + Tenant Self-Reporting Finalization | not-started | Menunggu foundation phase 4-8 stabil. |

## Evidence Phase 1 (Done)

1. Baseline keputusan produk final tersedia di [DECISION.md](DECISION.md).
2. Baseline implementasi dan gap aktif tersedia di [IMPLEMENTATION.md](IMPLEMENTATION.md).
3. Dokumen fitur bisnis-audit tersedia di [README.md](README.md).
4. Tracker phase dan clear criteria tersedia di [tracker.md](tracker.md).
5. UI/UX planning lintas role tersedia di [UI-UX-PLAN.md](UI-UX-PLAN.md).

## Evidence Phase 2 (Done)

1. Blueprint UI/UX lintas role tersedia di [UI-UX-PLAN.md](UI-UX-PLAN.md).
2. Screen map, critical journeys, dan state handling terdokumentasi lengkap (empty/warning/error/success).
3. Cross-check checklist Product, UX/UI, Frontend, Backend, QA, Security/Compliance sudah tertulis sebagai acceptance gate phase.
4. Definition of Done UI/UX sudah ditetapkan sebagai acuan implementasi.

## Evidence Phase 3 (Done)

1. Dokumen kontrak API tax governance sudah tersedia di [../../api/tax-governance-api.md](../../api/tax-governance-api.md).
2. OpenAPI sudah memuat endpoint draft tax governance (policy lifecycle, governance observability, break-glass, tenant self-audit report, platform billing tax report/export) di [../../api/openapi.yaml](../../api/openapi.yaml).
3. Mapping endpoint -> permission code sudah terdokumentasi untuk cross-check FE/BE/QA.
4. Guard identifier pada kontrak phase 3 sudah UUID-only (public contract).

### Follow-up Dipindah ke Phase 4

1. Wiring route/controller runtime sesuai kontrak phase 3.
2. Implementasi server-side enforcement RBAC, SoD, tenant boundary.
3. Backend test implementation untuk validasi kontrak.

## Evidence Phase 4 (In Progress)

1. Migration baru tax governance sudah ditambahkan untuk policy + immutable event log.
2. Runtime endpoint lifecycle tenant (`list/create/detail/update/submit/approve/publish`) sudah mulai diwire di API route/controller.
3. Enforcement SoD maker-checker (`TAX_POLICY_SOD_VIOLATION`) dan tenant-scoped object lookup (`TAX_POLICY_NOT_FOUND` cross-tenant) sudah terpasang baseline.
4. Baseline feature tests backend untuk lifecycle + permission + tenant boundary sudah ditambahkan.

## Evidence Terbaru

- `/tax-rates` ada di web route admin dan view aktif, tetapi halaman masih statis tanpa API/runtime owner.
- `EmployeeSnapshotService` menyimpan tax profile karyawan ke `employee_tax_profiles`.
- `HcmEmployeeController` import memvalidasi `tax_status` dan menolak nilai di luar enum yang didukung.
- `HcmSalaryComponentController` expose flag `includePph21TerGross` dan `includePph21AnnualReconciliation` yang memengaruhi basis pajak.
- `PayrollDraftBuilder` menghitung PPh21 TER bulanan dan mencatat metadata `missingTaxProfile`, `taxStatusSource`, serta `pph21TerCategory` pada payroll line.
- Dokumentasi API payroll dan salary component sudah mengakui peran tax status dan TER gross flags, tetapi belum ada paket fitur governance pajak yang memusatkan narasi audit.
- Decision document final sudah dibuat di [DECISION.md](DECISION.md) dan mengunci dual-plane architecture + UUID-only strategy.

## Gap Aktif

1. `/tax-rates` belum punya backing model/API sehingga tidak bisa dianggap source of truth audit.
2. Governance pajak tersebar ke employee master, salary component master, dan payroll engine tanpa dashboard kontrol terpadu.
3. Belum ada register anomaly terpusat untuk coverage tax profile, drift komponen taxable, dan annual reconciliation readiness.
4. Belum ada test negatif/UX warning yang menegaskan bahwa `/tax-rates` bukan control plane aktif.
5. Belum ada billing tax engine formal untuk biaya layanan aplikasi berbasis package cycle monthly/yearly/custom.
6. Belum ada reporting pack standar tenant self-audit vs platform billing tax.

Update keputusan:
- Gap keputusan arsitektur dinyatakan CLOSED karena dual-plane + dual-domain sudah dikunci di DECISION.

## Rencana Implementasi Berbasis Phase

Total phase sampai clear: **9 phase**.

### Phase 1 — Baseline Dokumentasi dan Scope Lock

- Tujuan: menyamakan pemahaman lintas tim soal boundary domain dan source of truth.
- Deliverable:
   - README, DECISION, IMPLEMENTATION, tracker sinkron.
   - matrix RBAC detail action-level.
   - catatan UUID-only strategy.
- Cross-check tim:
   - Product: lifecycle + domain boundary final.
   - Engineering Lead: anti-pattern dan implementation order disetujui.
- Exit gate:
   - semua dokumen baseline disetujui dan tidak conflict antar dokumen.

### Phase 2 — UI/UX Planning dan Role Journey

- Tujuan: punya blueprint UI/UX yang bisa dipakai FE/BE/QA tanpa ambiguity.
- Deliverable:
   - dokumen [UI-UX-PLAN.md](UI-UX-PLAN.md).
   - screen map, warning/error state, role journey, DoD UI/UX.
- Cross-check tim:
   - UX: flow tiap role valid.
   - Product: copy status bisnis dan keputusan aksi sensitif final.
- Exit gate:
   - tidak ada lagi gap interpretasi layar target per role.

### Phase 3 — API Contract dan Permission Mapping

- Tujuan: mengunci kontrak endpoint UUID-only + authorization map.
- Deliverable:
   - dokumen API tax governance.
   - update `docs/api/openapi.yaml`.
   - matrix endpoint -> permission code.
- Cross-check tim:
   - Backend: contract + validator + envelope konsisten.
   - QA: test matrix per endpoint tersedia.
- Exit gate:
   - openapi + feature API docs sinkron dan lolos script check.

### Phase 4 — Runtime Control Plane Foundation

- Tujuan: membangun source of truth tax policy runtime.
- Deliverable:
   - implementasi route/controller dari kontrak phase 3;
   - implementasi test matrix RBAC/SoD/tenant boundary;
   - model data UUID-first.
   - workflow draft/review/approve/publish/supersede.
   - effective-dated versioning.
- Cross-check tim:
   - Backend: SoD dan object-level tenant scope enforced.
   - Security: privileged action teraudit.
- Exit gate:
   - policy published bisa dibaca engine runtime secara deterministik.

### Phase 5 — Governance Dashboard Lintas Tenant

- Tujuan: visibilitas compliance posture lintas tenant subscribe untuk role global.
- Deliverable:
   - projection dashboard + metrik anomaly utama.
   - drill-down read-only per tenant.
- Cross-check tim:
   - Product: indikator risk/review relevan bisnis.
   - Security: data isolation tetap aman.
- Exit gate:
   - global dashboard tampil stabil tanpa melanggar tenant boundary.

### Phase 6 — Negative Path Hardening

- Tujuan: mencegah salah operasional dan false assurance.
- Deliverable:
   - warning banner anti-misleading pada `/tax-rates`.
   - negative tests role/authorization/flow.
   - copy UX error/warning final.
- Cross-check tim:
   - QA: negative scenario coverage lengkap.
   - Product + UX: pesan user-facing disetujui.
- Exit gate:
   - skenario misuse utama sudah gagal secara terkontrol.

### Phase 7 — Audit Evidence Pack

- Tujuan: menyiapkan artefak audit tenant dan governance platform.
- Deliverable:
   - export tenant self-audit pack.
   - anomaly register terpusat.
   - publication/change history evidence.
- Cross-check tim:
   - Compliance: struktur evidence cukup untuk audit.
   - QA: validasi data report vs runtime source.
- Exit gate:
   - evidence dapat diunduh dan ditelusuri end-to-end.

### Phase 8 — UUID Migration Execution

- Tujuan: menutup jalur numeric exposure pada domain tax governance.
- Deliverable:
   - backfill UUID, bridge internal, telemetri legacy path.
   - deprecate numeric contract.
- Cross-check tim:
   - Backend: data migration aman.
   - Ops: rollout dan monitoring migrasi jelas.
- Exit gate:
   - endpoint governance publik tidak mengekspos numeric ID.

### Phase 9 — Platform Billing Tax + Tenant Self-Reporting Finalization

- Tujuan: menutup domain platform billing tax sekaligus tenant reporting readiness.
- Deliverable:
   - billing tax policy engine untuk monthly/yearly/custom.
   - billing tax report lintas tenant untuk platform.
   - tenant self-reporting endpoint/report template final.
- Cross-check tim:
   - Product Finance: output billing tax sesuai kebutuhan revenue ops.
   - Tenant Ops: self-reporting usable untuk audit internal.
- Exit gate:
   - kedua domain (tenant statutory + platform billing) sama-sama operasional.

## Cross-team Tracking Board (Untuk Kroscek Tim)

Gunakan status per phase: `not-started`, `in-progress`, `blocked`, `ready-for-review`, `done`.

1. Product
    - Validasi lifecycle bisnis, policy semantics, dan decision log.
2. UX/UI
    - Validasi screen flow, empty/error/warning state, dan copy final.
3. Frontend
    - Implementasi route/view/state sesuai UI/UX plan + permission-aware UX.
4. Backend
    - Implementasi API UUID-only, RBAC guardrails, SoD, audit events, projection.
5. QA
    - UAT role-based, regression payroll impact, negative path, export evidence.
6. Security/Compliance
    - Validasi tenant isolation, break-glass, immutable trail, compliance reporting.
7. DevOps/Ops
    - Monitoring rollout, migration safety, dan release checklist.

## Clear Criteria (Sampai Selesai)

Semua phase dinyatakan clear jika seluruh poin berikut terpenuhi:

1. Phase 1-9 status `done` tanpa blocker terbuka.
2. API contract dan OpenAPI sinkron untuk endpoint baru tax governance.
3. RBAC action-level terpasang server-side dan teruji.
4. UI/UX tidak menyisakan halaman misleading pada `/tax-rates`.
5. Tenant self-audit dan platform billing tax report bisa dihasilkan dengan evidence yang dapat diverifikasi.

## Risiko Utama

1. Under/over withholding bila tax status kosong atau salah tetapi payroll tetap diproses dengan fallback.
2. Drift basis pajak bila komponen payroll bertambah tanpa review flag TER.
3. False assurance ke operator/auditor karena adanya menu `/tax-rates` yang terlihat seolah aktif.
4. Sulit melakukan root-cause analysis saat ada sengketa slip karena evidence tersebar di beberapa modul.

## Test & Evidence Plan

1. Dokumentasi dan matrix sync review untuk memastikan semua route/feature docs selaras.
2. Jika nanti ada perubahan runtime, tambahkan minimal:
   - test API untuk governance source baru;
   - test payroll regression untuk efek perubahan tax profile dan salary component flags;
   - test negative-path bahwa `/tax-rates` tidak misleading.

## Exit Criteria

1. Semua stakeholder sepakat source of truth pajak resmi di aplikasi.
2. `/tax-rates` tidak lagi ambigu: menjadi modul runtime nyata atau dashboard governance eksplisit.
3. Ada evidence anomaly coverage yang dapat dipakai audit tanpa harus membaca banyak tabel secara manual.