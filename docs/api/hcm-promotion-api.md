# HCM Promotion API (Phase 1)

Base path: `/v1/hcm`

## RBAC

- **HCM Admin**: list penuh `GET /promotions`, create/update/delete, serta `GET /promotions/{id}` dan `GET /promotions/users/{userId}/promotions` untuk semua user.
- **Karyawan (non-admin)**: **tidak** boleh `GET /promotions` (list admin), **tidak** boleh mutasi.
- **Karyawan** boleh:
  - `GET /promotions/{id}` hanya jika `promotion.user_id === auth.id`
  - `GET /promotions/users/{userId}/promotions` hanya jika `userId === auth.id`
- Semua aturan di atas harus di-enforce di backend (403 bila melanggar).

## Data model (ringkas)

Promotion record menyimpan riwayat promosi employee.

- `userId` (required): target employee (User)
- `department` (optional, string ≤ 150)
- `designationFrom` (optional, string ≤ 150)
- `designationTo` (optional, string ≤ 150)
- `promotionDate` (required, `YYYY-MM-DD`)
- `notes` (optional, string ≤ 2000)

## Endpoints

### GET `/promotions`

List promotion records (**HCM admin only**).

**Query**
- `q` (optional): cari pada department/designation/name/email
- `dateFrom` (optional, date)
- `dateTo` (optional, date)
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
      "designationFrom": "Accountant",
      "designationTo": "Sr Accountant",
      "promotionDate": "2026-04-09",
      "notes": "Congrats",
      "createdAt": "2026-04-09T10:20:30Z"
    }
  ],
  "meta": { "currentPage": 1, "lastPage": 1, "perPage": 20, "total": 1 }
}
```

### GET `/promotions/{id}`

Detail satu promotion.

- **Admin**: semua id.
- **Karyawan**: hanya baris milik sendiri (`user_id` = user login).

**404**: `PROMOTION_NOT_FOUND`  
**403**: `AUTH_FORBIDDEN`

**200**: body `data` sama shape seperti satu elemen di list.

### GET `/promotions/users/{userId}/promotions`

Riwayat promotion untuk satu employee (paginated).

- **Admin**: semua `userId`.
- **Karyawan**: hanya `userId` = diri sendiri.

**Query**: `perPage` optional 1..100 (default 20).

**404**: user tidak ada (`findOrFail`).

### POST `/promotions`

Create a promotion record (**HCM admin only**).

**Request**

```json
{
  "userId": 2,
  "department": "Finance",
  "designationFrom": "Accountant",
  "designationTo": "Sr Accountant",
  "promotionDate": "2026-04-09",
  "notes": "Congrats"
}
```

**201 Response**

```json
{ "success": true, "data": { "id": 10 } }
```

### PUT `/promotions/{id}`

Update a promotion record (partial update, **HCM admin only**).

**Request example**

```json
{ "designationTo": "Lead Accountant" }
```

**200 Response**

```json
{ "success": true }
```

### DELETE `/promotions/{id}`

Delete a promotion record (**HCM admin only**).

**200 Response**

```json
{ "success": true }
```

