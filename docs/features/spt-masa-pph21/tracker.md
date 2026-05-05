# SPT Masa PPh 21 Tracker

## Status Snapshot

- Date: 2026-05-04
- Status: Planning
- Focus saat ini: finalisasi blueprint 3 fase (FE -> BE UUID-only -> integrasi)

## Phase Progress

## Phase 1 - Build FE

- Status: Not Started
- Scope:
  - list page + detail page
  - state machine badge/action
  - wiring generate/regenerate/ready/submit/export
- Evidence: belum ada implementasi runtime

## Phase 2 - Build BE (UUID-only)

- Status: Not Started
- Scope:
  - migration + model + service + controller
  - UUID-only endpoint contract
  - snapshot generation + validations
- Evidence: belum ada implementasi runtime

## Phase 3 - Integrasi + Build Gate

- Status: Not Started
- Scope:
  - integrasi payroll lock + pph21 snapshot
  - export CSV validation
  - test evidence backend/frontend/manual E2E
- Evidence: belum ada implementasi runtime

## Open Gaps

- Belum ada tabel `hcm_spt_masa_headers` / `hcm_spt_masa_details` di schema runtime.
- Belum ada endpoint API `/v1/hcm/spt-masa/*`.
- Belum ada halaman UI SPT Masa di `backend/resources/views/spt-masa-pph21/`.
- Belum ada kontrak OpenAPI untuk SPT Masa di `docs/api/openapi.yaml`.
- Belum ada permission codes `tax.spt.view` / `tax.spt.manage` di matrix `docs/planning/active-hcm-templates-and-permissions.md`.

## Pre-Coding Decisions (locked)

- Gate generate = `hcm_payroll_runs.status = finalized` (BUKAN `locked`); `hcm_payroll_periods.status = posted` opsional untuk closure penuh.
- PK numeric `id` + kolom `uuid` UNIQUE; route binding pakai UUID (`{sptRef}`), FK internal pakai numeric id mengikuti pola `HcmPayrollLine`.
- Sumber data: `hcm_payroll_lines` final; bruto = sum addition `pph21_taxable_*`, pph21 = sum deduction `pph21_*`. Tidak membuat tabel `payroll_result`/`pph21_result`.
- Idempotency via `generationKey`; concurrency via `version` (optimistic lock) di setiap mutating op.
- MVP klasifikasi: `permanent` + `contract` saja. `intern` dan `non_employee` ditunda fase lanjutan via tabel opsional `hcm_bukti_potong`.
- API guard: `api.token` + `tenant.context`. Web guard: `hcm.web.admin`.
- Routes file baru: `backend/routes/api/spt-masa.php` dengan prefix `v1/hcm/spt-masa`.
- Negative codes wajib: `SPT_PAYROLL_NOT_FINAL`, `SPT_HEADER_DUPLICATE`, `SPT_INVALID_TRANSITION`, `SPT_VERSION_CONFLICT`, `SPT_DETAIL_INCOMPLETE`, `SPT_TOTAL_MISMATCH`.

## Definition of Done (MVP)

- Generate SPT dari payroll `finalized` berjalan idempotent (`generationKey` + `version`).
- List + detail view aktif dan tenant-scoped via UUID public route.
- Export CSV tersedia dengan kolom: NPWP, NIK, Nama, Kategori SPT, Bukti Potong Type, Bruto, PPh21.
- Status flow `draft -> ready -> submitted` tervalidasi backend dengan optimistic lock.
- OpenAPI sync (`scripts/check-api-docs-sync.sh`) hijau.
- Permission matrix `docs/planning/active-hcm-templates-and-permissions.md` ter-update.
- Local gate lulus: `bash scripts/local-test-gate.sh`.
- Tracker ini di-update dengan evidence per fase.
