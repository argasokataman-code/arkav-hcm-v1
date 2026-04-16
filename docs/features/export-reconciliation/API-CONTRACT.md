# Export Reconciliation - API Contract (Draft)

Dokumen ini adalah kontrak awal endpoint untuk fitur Export Reconciliation.

## 1) Trigger Export

Endpoint generic (opsi):

- `POST /v1/reconciliation/exports`

Request body:

- `featureKey` (string, required)
- `actionKey` (string, required)
- `scopeRef` (string, required)
- `fileFormat` (`csv|xlsx|pdf`, required) — **alias:** `format` (kompatibilitas klien payroll/THR/PKWT)
- `filterPayload` (object, optional) — **alias:** `filters`
- `filePath` (string, optional untuk `csv`, required untuk `xlsx|pdf`)
  - Jika `csv` dan `filePath` kosong: server menulis file ke `reconciliation/generated/...` pada disk `local`.
- `rowCount` (integer, optional)
  - Jika kosong: diinfer dari `filterPayload` (mis. panjang `lineIds` / `periods`) atau `0`.
- `datasetChecksum` (string, optional)
  - Jika kosong pada mode auto-`csv`: dihitung deterministik dari payload inti + `filterPayload`.

Response 201:

- `success: true`
- `data` (object evidence), minimal:
  - `id`
  - `filePath`
  - `fileFormat`
  - `rowCount`
  - `datasetChecksum`
  - `exportedAt`

Error:

- 401 `UNAUTHORIZED`
- 403 `FORBIDDEN`
- 422 `VALIDATION_ERROR`

## 2) Validate Gate Before Action

Endpoint generic (opsi):

- `POST /v1/reconciliation/validate-gate`

Request body:

- `featureKey` (string, required)
- `actionKey` (string, required)
- `scopeRef` (string, required)
- `context` (object, optional)

Response 200:

- `success: true`
- `data.valid` (boolean)
- `data.reason` (nullable)
- `data.evidence` (nullable)

Jika invalid (422):

- `EXPORT_RECON_REQUIRED`
- `EXPORT_RECON_EXPIRED`
- `EXPORT_RECON_SCOPE_MISMATCH`
- `EXPORT_RECON_STALE_DATA`

## 3) List Evidences

- `GET /v1/reconciliation/exports`

Query params:

- `featureKey` (optional)
- `actionKey` (optional)
- `scopeRef` (optional)
- `page`, `perPage`

Response 200:

- daftar evidence + pagination metadata

## 4) Download Evidence

- `GET /v1/reconciliation/exports/{id}/download`

Response 200:

- file stream CSV/XLSX

---

## Integrasi ke Endpoint Existing

Validasi gate dipanggil sebelum menjalankan:

- payroll finalize/disburse
- THR disburse/post-payroll
- PKWT post-payroll/pay run
- invoice mark-paid
- payment verify

Pilihan implementasi:

1. Middleware khusus per action route.
2. Service check di awal method controller.

---

## Tenant & RBAC Rules

- Seluruh evidence wajib tenant-scoped.
- User hanya bisa melihat/download evidence tenant sendiri.
- Action export + action bisnis tetap mengikuti role admin yang berlaku.

---

## Catatan OpenAPI

Saat implementasi dimulai, file berikut wajib disinkronkan:

- `docs/api/openapi.yaml`
- `docs/api/*` terkait payroll dan purchase transaction
