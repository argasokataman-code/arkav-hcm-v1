# Tax Governance — Status Tracker

## Snapshot Status (2026-05-03)

| Komponen | Status | Evidence |
|---|---|---|
| Policy CRUD (draft/edit) | ✅ Done | PHPUnit HcmTaxGovernancePhase7Test — 6 tests pass |
| Policy lifecycle (publish/supersede/void) | ✅ Done | Controller + migration tersedia |
| Workflow multi-step (submit/approve/reject) | ✅ Done | Endpoint aktif + PHPUnit HcmTaxGovernanceWorkflowApiTest |
| Compliance snapshot (`tenant-compliance-status`) | ✅ Done | Termasuk `employee_pph21_compliance` |
| Employee PPh21 profile quality metrics | ✅ Done | NPWP valid/invalid/missing, PTKP, completion rate |
| Self-audit report enhanced | ✅ Done | Endpoint aktif |
| Self-audit export (json/pdf) | ✅ Done | Endpoint aktif |
| Anomaly registry (tenant) | ✅ Done | resolve + acknowledge tersedia |
| Revenue capture event-driven | ✅ Done | SubscriptionCreated/AddOnPurchased listeners aktif |
| Platform billing policies/reports/invoices | ✅ Done | Endpoint aktif |
| Government layer (platform-tax-compliance) | ✅ Done | Endpoint aktif |
| Platform tax reporting (`/saas/platform-tax`) | ✅ Hardening pass 2026-05-15 | Rumus PPN/PPh23 diluruskan, deadline UI surfaced, export/docs sinkron |
| Frontend halaman employee tax profiles | ✅ Done | `/tax-employees/employee-tax-profiles` aktif |
| Frontend halaman reports | ✅ Done | `/tax-employees/reports` aktif |
| Frontend policy authoring UI | ✅ Done | `/tax-employees/policies` + editor UUID ter-wire |
| Dashboard lintas tenant | ✅ Done | Route aktif + PHPUnit HcmTaxGovernanceWorkflowApiTest |
| Break-glass flow | ✅ Done | Route + controller + persistence + PHPUnit HcmTaxGovernanceWorkflowApiTest |
| UUID migration — policyRef | ✅ Done | Runtime UUID-only |
| API docs sync (openapi.yaml) | ✅ Done | Sync per 2026-05-02 |
| Feature docs (README + IMPLEMENTATION + tracker) | ✅ Done | Dibuat 2026-05-03 |

## Gap Summary

### Status Saat Ini
1. Semua gap prioritas 2026-05-03 sudah ditutup di runtime, UI, dan regression test terfokus.
2. Sisa follow-up hanya pengayaan non-blocking: approval chain multi-party formal dan inferensi PTKP berbasis data tanggungan.

## Changelog

| Tanggal | Perubahan | Evidence |
|---|---|---|
| 2026-05-02 | Tambah `employee_pph21_compliance` ke `tenantComplianceStatus()` | HcmTaxGovernancePhase7Test test baru pass |
| 2026-05-02 | Perkeras validasi NPWP di `HcmEmployeeController` (15-16 digit) | HcmEmployeeApiTest 39 tests pass |
| 2026-05-02 | Sync `docs/api/tax-governance-api.md` dengan `employee_pph21_compliance` response | check-api-docs-sync.sh OK |
| 2026-05-03 | Buat `docs/features/tax-governance/` (README, IMPLEMENTATION, tracker) | — |
| 2026-05-03 | Aktifkan workflow submit/approve/reject/publish + UUID-only policyRef | HcmTaxGovernanceWorkflowApiTest 4 tests pass |
| 2026-05-03 | Aktifkan break-glass flow + dashboard route + auto-PTKP default | HcmTaxGovernanceWorkflowApiTest 4 tests pass |
| 2026-05-15 | Hardening `/saas/platform-tax`: perbaikan basis DPP tax-inclusive, filter invoice nol, deadline bulanan/tahunan di UI, parity export & docs sync | PlatformTaxSummaryApiTest + platform-tax.wiring.test.js |
