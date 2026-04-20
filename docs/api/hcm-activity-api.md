# HCM Activity Feed API

Prefix: `/v1/hcm` · middleware `api.token` + `tenant.context` · envelope `{ success, data?, error?, meta? }`.

## Ringkasan

Endpoint ini memberi feed aktivitas tenant yang dipakai halaman web `/activity`.

Sumber event saat ini:
- `asset_logs` (aktivitas lifecycle asset)
- `hcm_user_role_audits` (aktivitas role/akses user)
- `hcm_payroll_runs` (aktivitas payroll draft/finalize)

Feed digabung, diurutkan descending berdasarkan waktu event, lalu dipaginasi.

## RBAC

- **View feed (`GET /activity-feed`)**:
  - Global HCM admin/super admin selalu boleh akses.
  - Owner/admin tenant boleh akses sesuai tenant aktif.
  - Employee/member harus punya permission tenant yang sesuai.
- **Mutasi manual activity (`POST/PUT/DELETE /activity-manual*`)**:
  - **Global HCM admin/super admin** boleh create/update/delete.
  - **Owner/admin tenant aktif** juga boleh create/update/delete untuk tenant aktif.
  - Employee/member tenant tanpa hak admin akan menerima `403` (`AUTH_FORBIDDEN`).

## Endpoint

### `GET /activity-feed`

Query opsional:
- `type`: `all | asset | user_access | payroll | manual` (default `all`)
- `sourceType`: `all | system | manual` (default `all`)
- `statusType`: status activity (contoh `created`, `updated`, `assigned`, `finalized`)
- `companyId`: filter company khusus (opsional, hanya untuk global admin; jika kosong maka scope semua company)
- `q`: keyword pencarian
- `page`: integer >= 1 (default `1`)
- `perPage`: integer 1..100 (default `20`)

Response 200:

```json
{
  "success": true,
  "data": [
    {
      "id": "user-access-77",
      "title": "Assigned HR Access",
      "activityType": "user_access",
      "activityTypeLabel": "User Access",
      "sourceType": "manual",
      "sourceTypeLabel": "Manual",
      "statusType": "assigned",
      "statusTypeLabel": "Assigned",
      "readOnlyReason": null,
      "companyId": 12,
      "companyName": "Acme Indonesia",
      "companyCode": "acme-id",
      "dueDate": null,
      "ownerName": "Activity Admin",
      "createdAt": "2026-04-15T05:11:12+00:00"
    }
  ],
  "meta": {
    "page": 1,
    "perPage": 20,
    "total": 1,
    "totalPages": 1
  }
}
```

Error:
- `401` unauthorized
- `403` forbidden (non-admin)
- `422` validation error atau tenant context tidak valid

### `GET /activity-feed-companies`

List company aktif untuk dropdown filter activity feed.

- Hanya global HCM admin/super admin.
- Non-admin: `403 AUTH_FORBIDDEN`.
- Response berisi list `{ id, code, name, status, createdAt }`.

### `POST /activity-manual`

Membuat activity manual (editable) oleh global admin atau owner/admin tenant aktif.

Body:
- `title` (required)
- `activityKind`: `task | call | email | meeting | note`
- `statusType`: `planned | in_progress | completed | cancelled`
- `dueDate` (optional, date)

### `PUT /activity-manual/{id}`

Update activity manual milik tenant aktif oleh global admin atau owner/admin tenant aktif.

### `DELETE /activity-manual/{id}`

Hapus activity manual milik tenant aktif oleh global admin atau owner/admin tenant aktif.

## Aturan Edit/Hapus

- Record `sourceType = manual` dapat diedit/dihapus.
- Record `sourceType = system` **read-only** (hanya tampil di feed).
- Field `readOnlyReason` berisi alasan read-only untuk record system.
- Untuk user non-admin tenant, row manual tetap tampil tetapi `canEdit/canDelete = false` dengan `readOnlyReason` yang menjelaskan pembatasan akses.
