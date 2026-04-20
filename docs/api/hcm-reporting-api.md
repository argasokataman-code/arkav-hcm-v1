# HCM Reporting API

Base path aktif untuk reporting terbagi dua surface:

- Snapshot HCM: `/v1/hcm/reports/snapshots`
- Legacy report API untuk halaman report lama: `/v1/saas/reports/*`

## Source of truth

- Runtime: `backend/routes/api.php`
- Controller: `ReportSnapshotController` + `ReportController`
- OpenAPI: `docs/api/openapi.yaml`

## Snapshot API

### POST `/v1/hcm/reports/snapshots`

Generate snapshot report.

Request body:

```json
{
  "reportType": "employee",
  "periodStart": "2026-03-20",
  "periodEnd": "2026-04-19",
  "filters": {},
  "async": false
}
```

Rules:

- bearer auth wajib
- tenant context aktif (`X-Company-Id`) wajib
- server-side authorization: `reports.view`

### GET `/v1/hcm/reports/snapshots`

List snapshot per company aktif.

Query:

- `page`, `perPage`
- `reportType`: `attendance|payroll|employee|leave|finance`
- `status`: `pending|processing|completed|failed`

### GET `/v1/hcm/reports/snapshots/{id}`

Detail snapshot.

- `id` menerima UUID atau numeric legacy fallback.
- snapshot company lain dikembalikan `404 SNAPSHOT_NOT_FOUND`.

### POST `/v1/hcm/reports/snapshots/{id}/export`

Generate file export dari snapshot.

Request:

```json
{ "fileType": "csv" }
```

Rules:

- `id` menerima UUID atau numeric legacy fallback.
- snapshot harus `completed`, jika belum: `422 SNAPSHOT_NOT_READY`.
- snapshot company lain: `404 SNAPSHOT_NOT_FOUND`.

## Legacy report API

Endpoint ini masih dipakai halaman report lama seperti `invoice-report`, `payment-report`, `expenses-report`, `user-report`, `daily-report`, `project-report`, dan `task-report`.

### GET `/v1/saas/reports/revenue`

Query:

- `period`: `monthly|yearly` (default `monthly`)
- `year`: integer (untuk `yearly`)
- `company_id`: optional numeric company id

### GET `/v1/saas/reports/aging`

Query:

- `company_id`: optional numeric company id

### GET `/v1/saas/reports/churn`

Query:

- `period`: `monthly|yearly` (default `monthly`)
- `year`: integer (untuk `yearly`)
- `company_id`: optional numeric company id

Rules untuk legacy report API:

- bearer auth wajib
- server-side authorization: `report.view`
- backward compatible: tanpa `X-Company-Id`, endpoint tetap bisa memakai `company_id` query seperti sebelumnya
- bila request membawa `X-Company-Id`, backend mengunci scope ke company aktif dari header itu
- bila `company_id` query berbeda dari `X-Company-Id`, backend mengembalikan `403 TENANT_SCOPE_MISMATCH`

## Negative scenarios yang sudah diverifikasi

- non-admin tidak bisa akses snapshot generate (`403 AUTH_FORBIDDEN`)
- snapshot company lain tidak bisa dibaca/export walaupun id diketahui (`404 SNAPSHOT_NOT_FOUND`)
- legacy revenue report tidak bisa dioverride ke company lain saat active tenant header sudah ada (`403 TENANT_SCOPE_MISMATCH`)