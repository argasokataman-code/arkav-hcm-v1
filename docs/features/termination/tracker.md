# Termination Progress Tracker

## Snapshot 2026-05-28

- Status: **standalone package feature — sellable separately**
- Focus: registrasi `termination` sebagai feature_code mandiri di sistem package/subscription.

### Package Registration

- Feature code baru: `termination` (sebelumnya bundled ke `employee_lifecycle`)
- Migration: `2026_05_28_000100_register_termination_as_standalone_package_feature.php`
- Paket yang mendapat akses: `business`, `enterprise`, `ultimate`, `umkm`, `unlimited`
- Paket tanpa akses (perlu upgrade/beli terpisah): `starter`, `growth`, `trial`

### Route Gate Changes

| Komponen | Sebelum | Sesudah |
|----------|---------|---------|
| API route (`/v1/hcm/terminations`) | `hcm.api.feature:employee_lifecycle` | `hcm.api.feature:termination` |
| Web route (`/termination`) | `hcm.web.admin` only | `hcm.web.admin` + `hcm.web.feature:termination` |

### Docs Updated

- `docs/features/RUNTIME-FEATURE-CLASSIFICATION.md` — termination listed as active add-on with package scope
- `docs/planning/active-hcm-templates-and-permissions.md` — termination row updated with feature_code + gate info

---

## Snapshot 2026-05-26

- Status: enrichment implemented (Slice A + B + C)
- Focus: perkaya settlement calculation (severance + leave payout), workflow audit trail, dan checklist item management endpoints.
- Catatan: formula final wajib divalidasi Legal/Industrial Relations perusahaan sebelum live.

### Implemented (Slice A — Settlement Enrichment)
- `TerminationSettlementCalculationService` — policy-based UP/UPMK/UPH + leave payout calculation
- `TerminationSettlementBreakdown` DTO
- `config/termination-policy-profiles.php` — 7 policy profiles per reason code + legal basis
- `settlement_evidence_snapshot` — immutable snapshot of formula inputs (Anomaly #1)
- `leave_balance_available` boolean — flag when leave balance unavailable (Anomaly #4)
- Migration: `2026_05_26_000001_add_settlement_evidence_fields_to_hcm_terminations`

### Implemented (Slice B — Workflow Audit Trail)
- `WorkflowAuditEvent` DTO
- `TerminationWorkflowValidator` — strict sequential stage transition enforcement
- `workflow_history` JSON + `workflow_version` integer (optimistic lock)
- DB::transaction wraps workflow_history mutations (Anomaly #2 concurrent write protection)
- Strict stage transitions: no skipping allowed (`draft_review → finalized_execution` is blocked)
- Migration: `2026_05_26_000002_add_workflow_history_to_hcm_terminations`

### Implemented (Slice C — Checklist Item Endpoints)
- `HcmTerminationChecklistItem` model with SoftDeletes + AssignsUuid
- 5 endpoints: POST/GET `/checklist-items`, PATCH/DELETE `/{itemId}`, PATCH `/{itemId}/complete`
- Finalization blocked if any mandatory DB checklist item is still `open` (422 `MANDATORY_CHECKLIST_INCOMPLETE`)
- Migration: `2026_05_26_000003_create_hcm_termination_checklist_items_table`

### Test Results (2026-05-26)
- `vendor/bin/phpunit tests/Feature/TerminationApiTest.php` → 20 tests, 234 assertions ✅
- Full PHPUnit gate: 1086 tests ✅
- Vitest gate: 241 tests ✅

### Pending (documented gaps)
- **Anomaly #8**: PPh21 deduction harus menggunakan `PayrollTaxCalculationService` (service belum ada). Saat ini deduction hanya dari `finalDeductionAmount` user input. Tracked via `@todo` di `TerminationSettlementCalculationService.php` line ~193.
- Role-based stage transition enforcement (planning §5.2.1: "non-legal cannot approve legal_review") — not yet implemented, requires role taxonomy decision from IR/Legal.

## Snapshot 2026-04-26

- Status: in progress (compliance hardening)
- Focus: matangkan flow Termination agar siap dipakai sebagai proses PHK yang selaras praktik ketenagakerjaan Indonesia, sambil tetap menjaga compatibility runtime existing.
- Catatan: baseline ini adalah guidance produk/engineering; final legal wording dan formula wajib divalidasi Legal/Industrial Relations perusahaan.

## Snapshot 2026-04-19

- Status: in progress
- Focus: hubungkan lifecycle `finalized` ke source runtime payroll period, settlement breakdown, dan clearance item terstruktur.

## Implemented

- Status `finalized` diterima end-to-end di controller, docs, UI modal, dan list/detail Termination.
- Termination `finalized` sekarang dapat menyimpan snapshot settlement manual:
  - payroll period target
  - final salary amount
  - final allowance amount
  - final deduction amount
  - server-computed net payable
  - asset return notes
  - clearance notes
- Termination `finalized` sekarang juga dapat me-refresh preview settlement dari source runtime:
  - auto-resolve payroll period aktual terdekat dan simpan `payrollPeriodId`
  - hitung gaji pokok dan tunjangan tetap secara prorata sampai `terminationDate`
  - gunakan payroll run bulanan terdekat sebagai reference line untuk komponen tambahan/potongan bila tersedia
  - tambahkan kompensasi PKWT bila profile contract memang due pada bulan termination
  - simpan `clearanceItems` terstruktur dari asset assignment aktif yang belum return
- List Termination menampilkan ringkasan settlement untuk baris `finalized`.
- Detail Termination menampilkan breakdown settlement dan clearance item saat snapshot tersedia.
- Modal Termination punya tombol `Refresh from payroll & assets` untuk auto-fill final settlement.
- Clearance item asset sekarang bisa langsung di-return dari context Termination untuk record existing.

## Evidence

- Backend regression: `backend/tests/Feature/TerminationApiTest.php`
- Frontend API contract: `backend/tests/ui/termination-api-contract.test.js`
- Frontend page wiring: `backend/tests/ui/termination.wiring.test.js`
- Employee detail relation: `backend/tests/ui/employee-details-training.wiring.test.js`

## Latest Validation

- `php artisan migrate --force` → migrations `2026_04_27_000000_add_finalization_fields_to_hcm_terminations_table` dan `2026_04_27_010000_add_structured_settlement_fields_to_hcm_terminations_table` applied.
- `php artisan test tests/Feature/TerminationApiTest.php` → 7 passed, 87 assertions.
- `npm run test:ui -- tests/ui/termination-api-contract.test.js tests/ui/termination.wiring.test.js tests/ui/employee-details-training.wiring.test.js` → 3 files passed, 14 tests passed.
- `npm run build` → success.
- `scripts/check-api-docs-sync.sh` → no backend API surface changes detected.

## Open Gaps

- Settlement policy termination sekarang sudah lebih kaya, tetapi formula masih fokus ke prorata gaji pokok/tunjangan tetap + kompensasi PKWT; belum ada policy tambahan lain seperti severance, leave payout, atau komponen custom HR policy.
- Clearance asset sekarang bisa dipicu return langsung dari Termination, tetapi belum ada approval step/manual checklist lintas kewajiban non-asset per item.
- Preview settlement masih belum menggabungkan source lintas-purpose seperti THR atau run khusus lain bila bisnis ingin settlement final multi-source pada satu layar.

## Next Recommended Slice

- Tambahkan checklist/approval step lintas kewajiban non-asset dari context Termination.
- Tambahkan formula settlement policy lain seperti severance, leave payout, atau custom compensation policy bila HR membutuhkannya.
- Evaluasi agregasi source lintas-purpose seperti THR atau payroll run khusus lain bila settlement final perlu tampil multi-source pada satu preview.

## Compliance Hardening (Indonesia) — Mandatory Backlog

### Baseline regulasi yang harus dijadikan acuan implementasi

- UU Ketenagakerjaan dan perubahan terbarunya (termasuk kerangka Cipta Kerja).
- PP turunan terkait PKWT, alih daya, waktu kerja/istirahat, dan PHK (terutama struktur hak saat PHK dan kompensasi PKWT).
- Aturan turunan pengupahan yang mempengaruhi komponen final settlement.
- Praktik hubungan industrial (bipartit/mediasi) sebagai evidentiary trail ketika sengketa terjadi.

### Gap compliance prioritas tinggi (existing vs target)

- Belum ada klasifikasi alasan PHK yang terstruktur dan terhubung ke konsekuensi hak finansial.
- Formula hak PHK belum eksplisit memisahkan komponen seperti pesangon, UPMK, dan UPH sesuai kategori kasus.
- Belum ada guardrail tanggal/proses untuk tahapan bipartit, mediasi, dan status final yang siap eksekusi.
- Belum ada lampiran bukti wajib per kasus (surat keputusan, berita acara, bukti komunikasi, dasar perhitungan).
- Belum ada snapshot audit yang mengunci parameter hukum + formula version pada saat finalisasi.

### Slice implementasi yang direkomendasikan (urut mandatory)

1. Tambahkan taxonomy `terminationReasonCode` + `legalBasisCode` yang baku, dengan mapping ke policy settlement. ✅
2. Implement policy engine settlement yang memisahkan komponen hak (pesangon/UPMK/UPH/kompensasi PKWT/komponen internal) dan menyimpan formula input-output sebagai audit trail. 🔄 in progress
3. Tambahkan workflow compliance: draft → review IR/legal → approved internal → finalized execution, dengan validasi dokumen wajib per tahap. ✅
4. Tambahkan checklist kewajiban non-asset (handover pekerjaan, akses sistem, dokumen legal, payroll close items) dengan owner dan due date. 🚧
5. Tambahkan immutable snapshot fields untuk bukti: formula version, source payroll period, timestamp approval, approver actor, dan hash dokumen lampiran.
6. Perluas test matrix backend + UI untuk skenario hukum utama (termasuk negative case saat dokumen/fase wajib belum lengkap).
7. Sinkronkan docs API dan feature docs setelah setiap perubahan kontrak/field compliance.

### Delta Implementasi 2026-04-26 (Slice #1)

- Menambahkan field `termination_reason_code` dan `legal_basis_code` pada `hcm_terminations`.
- Menambahkan validasi enum server-side untuk `terminationReasonCode` dan `legalBasisCode` di endpoint create/update.
- Menambahkan response field `terminationReasonCode` dan `legalBasisCode` di payload list/detail.
- Menambahkan input taxonomy pada modal Termination (UI) + wiring payload frontend.
- Menambahkan regression test untuk skenario accepted values dan invalid values (422).

### Delta Implementasi 2026-04-26 (Slice #2 - Progress)

- Menambahkan field `policy_profile_key` + `policy_formula_version` pada `hcm_terminations`.
- Menambahkan mapper profile policy dari kombinasi `terminationReasonCode` + `legalBasisCode`.
- Menambahkan metadata `policyProfileKey` + `policyFormulaVersion` di payload response termination.

### Delta Implementasi 2026-04-26 (Slice #3)

- Menambahkan `workflow_stage` sebagai field compliance stage terpisah dari `status` legacy.
- Menambahkan audit trail actor/timestamp untuk review, approval, dan finalization.
- Menambahkan validasi transisi stage pada update termination.
- Menambahkan UI selector workflow stage dengan status turunan otomatis agar flow lama tetap kompatibel.
- Menambahkan regression test untuk audit trail workflow dan invalid transition.

### Delta Implementasi 2026-04-26 (Slice #4 - Foundation)

- Menambahkan `non_asset_checklist` pada snapshot settlement termination.
- Menambahkan payload `nonAssetChecklist[]` dengan field `label`, `ownerName`, `dueDate`, `status`, `completionEvidence`, dan `mandatory`.
- Menambahkan hard guard finalization: jika checklist mandatory dikirim, semua item wajib `completed` sebelum `finalized_execution`/`finalized` diterima.
- Menambahkan regression test untuk skenario checklist mandatory yang masih open vs sudah completed.
- Status masih foundation: persistence + contract + guard sudah aktif, tetapi UI editor checklist khusus belum dibuat.

### Evidence minimum sebelum status dinaikkan ke "compliance-ready"

- Regression test termination lulus untuk happy-path dan negative-path compliance.
- Bukti audit snapshot memuat formula version + legal metadata.
- UAT lintas HR, Payroll, dan Legal menyetujui minimal 3 skenario PHK yang berbeda.
- Dokumentasi fitur dan API sudah sinkron dengan field/proses compliance baru.

## Detailed To-Do Eksekusi Lanjutan (One by One)

### Slice #2 — Policy engine komponen hak PHK

- Deliverable teknis:
  - tambah policy mapper yang menerjemahkan kombinasi `terminationReasonCode` + `legalBasisCode` menjadi profile komponen hak (pesangon/UPMK/UPH/PKWT/internal);
  - simpan snapshot `policy_profile_key` + `policy_formula_version` saat finalized;
  - tampilkan metadata profile policy di payload detail.
- Test minimum:
  - positive case untuk minimal 3 profile policy berbeda;
  - negative case untuk kombinasi code tidak valid terhadap profile policy.
- Evidence wajib:
  - hasil PHPUnit termination suite;
  - update `docs/api/hcm-termination-api.md` + `docs/api/openapi.yaml` jika ada field baru.

### Slice #3 — Workflow compliance approval

- Deliverable teknis:
  - tambah workflow state `draft_review`, `legal_review`, `approved_internal`, `finalized_execution`;
  - validasi transisi status per stage;
  - simpan actor + timestamp untuk setiap approval stage.
- Test minimum:
  - transisi valid antar stage;
  - transisi invalid ditolak 422;
  - role tanpa permission tidak bisa approve stage.
- Evidence wajib:
  - unit/feature test transisi stage pass;
  - matriks role/permission docs sinkron.

### Slice #4 — Checklist non-asset obligations

- Deliverable teknis:
  - endpoint checklist item (create/update/complete) per termination;
  - field owner, dueDate, completionEvidence;
  - hard guard: status tidak bisa finalized_execution jika checklist wajib belum complete.
- Test minimum:
  - finalize diblok saat item wajib open;
  - finalize lolos saat semua mandatory complete.
- Evidence wajib:
  - scenario test compliance checklist pass;
  - update README flow bisnis termination.

### Slice #5 — Immutable legal snapshot

- Deliverable teknis:
  - simpan immutable hash attachment bundle;
  - lock formula/policy version ketika finalized_execution;
  - simpan `approvedBy`, `approvedAt`, `finalizedBy`, `finalizedAt`.
- Test minimum:
  - snapshot tidak bisa diubah setelah finalized_execution;
  - hash berubah jika lampiran berubah sebelum finalization.
- Evidence wajib:
  - regression test immutability pass;
  - audit payload ditampilkan konsisten pada API detail.