# HCM Resignation API (Phase 1)

Base path: `/v1/hcm`

## RBAC

- **HCM Admin**: list penuh `GET /resignations`, create/update/delete, serta `GET /resignations/{id}` dan `GET /resignations/users/{userId}/resignations` untuk semua user.
- **Karyawan (non-admin)**: **tidak** boleh `GET /resignations` (list admin), **tidak** boleh mutasi.
- **Karyawan** boleh:
  - `GET /resignations/{id}` hanya jika `resignation.user_id === auth.id`
  - `GET /resignations/users/{userId}/resignations` hanya jika `userId === auth.id`
- Semua aturan di atas harus di-enforce di backend (403 bila melanggar).

## Data model (ringkas)

- `userId` (required): target employee (User)
- `department` (optional, string ≤ 150)
- `reason` (required, string ≤ 2000)
- `noticeDate` (required, `YYYY-MM-DD`)
- `resignationDate` (required, `YYYY-MM-DD`, harus ≥ `noticeDate`)
- `status` (optional, default `pending`): `pending` | `approved` | `cancelled`
- `notes` (optional, string ≤ 2000)

## Endpoints

### GET `/resignations`

List resignation records (**HCM admin only**).

**Query**

- `q` (optional): cari pada department/reason/status/name/email user
- `dateFrom` / `dateTo` (optional, date): filter `resignation_date`
- `perPage` (optional, 1..100; default 20)

**200 Response**

```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "employee": { "id": 2, "name": "Employee User", "email": "employee@company.com" },
      "department": "Finance",
      "reason": "Career change",
      "noticeDate": "2026-04-01",
      "resignationDate": "2026-04-30",
      "status": "pending",
      "notes": "",
      "createdAt": "2026-04-09T10:20:30Z"
    }
  ],
  "meta": { "currentPage": 1, "lastPage": 1, "perPage": 20, "total": 1 }
}
```

### GET `/resignations/{id}`

Detail satu resignation.

- **Admin**: semua id.
- **Karyawan**: hanya baris milik sendiri (`user_id` = user login).

**404**: `RESIGNATION_NOT_FOUND`  
**403**: `AUTH_FORBIDDEN`

**200**: body `data` sama shape seperti satu elemen di list.

### GET `/resignations/users/{userId}/resignations`

Riwayat resignation untuk satu employee (paginated).

- **Admin**: semua `userId`.
- **Karyawan**: hanya `userId` = diri sendiri.

**Query**: `perPage` optional 1..100 (default 20).

**404**: user tidak ada (`findOrFail`).

### POST `/resignations`

Create (**HCM admin only**).

**Request**

```json
{
  "userId": 2,
  "department": "Finance",
  "reason": "Career change",
  "noticeDate": "2026-04-01",
  "resignationDate": "2026-04-30",
  "status": "pending",
  "notes": "Optional"
}
```

**201 Response**

```json
{ "success": true, "data": { "id": 10 } }
```

### PUT `/resignations/{id}`

Update (partial, **HCM admin only**). Jika mengubah pasangan tanggal, `resignationDate` harus tetap ≥ `noticeDate` (422 `VALIDATION_ERROR` bila melanggar).

**200 Response**

```json
{ "success": true }
```

### DELETE `/resignations/{id}`

Delete (**HCM admin only**).

**200 Response**

```json
{ "success": true }
```
