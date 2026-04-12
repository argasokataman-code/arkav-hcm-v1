# HCM Termination API (Phase 1)

Base path: `/v1/hcm`

## RBAC

- **HCM Admin**: list penuh `GET /terminations`, create/update/delete, serta `GET /terminations/{id}` dan `GET /terminations/users/{userId}/terminations` untuk semua user.
- **Karyawan (non-admin)**: **tidak** boleh `GET /terminations` (list admin), **tidak** boleh mutasi.
- **Karyawan** boleh:
  - `GET /terminations/{id}` hanya jika `termination.user_id === auth.id`
  - `GET /terminations/users/{userId}/terminations` hanya jika `userId === auth.id`

## Data model (ringkas)

- `userId` (required)
- `department` (optional, string ≤ 150)
- `terminationType` (required, string ≤ 150) — contoh: Retirement, Layoff, Insubordination
- `reason` (required, string ≤ 2000)
- `noticeDate`, `terminationDate` (required, `YYYY-MM-DD`; `terminationDate` ≥ `noticeDate`)
- `status` (optional, default `pending`): `pending` | `approved` | `cancelled`
- `notes` (optional, string ≤ 2000)

## Endpoints

### GET `/terminations`

List (**HCM admin only**). Query: `q`, `dateFrom`/`dateTo` (filter `termination_date`), `perPage` 1..100.

### GET `/terminations/{id}`

Detail. **404**: `TERMINATION_NOT_FOUND`. **403**: `AUTH_FORBIDDEN` untuk karyawan bukan pemilik baris.

### GET `/terminations/users/{userId}/terminations`

Riwayat per employee (paginated). **404** jika user tidak ada.

### POST `/terminations`

Create (**HCM admin only**). **201**: `{ "success": true, "data": { "id": … } }`.

### PUT `/terminations/{id}`

Update partial (**HCM admin**). Pasangan tanggal harus tetap valid (422 `VALIDATION_ERROR` jika `terminationDate` < `noticeDate`).

### DELETE `/terminations/{id}`

Delete (**HCM admin**). **200**: `{ "success": true }`.
