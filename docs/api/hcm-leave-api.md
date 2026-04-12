# Leave API (Phase 1)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/HcmLeaveRequestController.php` + `backend/app/Http/Controllers/Api/HcmLeaveSettingController.php`.

## Base path

`/v1/hcm`

## Leave type options (untuk form request)

### GET `/leave-type-options`

RBAC:
- Authenticated: **allowed** (admin & employee)

Success `200`:

```json
{
  "success": true,
  "data": [
    { "code": "sick_leave", "name": "Sick Leave" }
  ]
}
```

## Leave requests

Resource: `/leave-requests`

Status:
- `pending` (default)
- `approved`
- `declined`

### GET `/leave-requests`

Query:
- `scope`: `me` (optional)
- `page` optional int ≥1
- `perPage` optional int 1..100 (default 20)
- `leaveType` optional string
- `status` optional `pending|approved|declined`
- `dateFrom` optional date
- `dateTo` optional date
- `userId` optional integer (admin only)

Catatan filter `leaveType`:
- Server menormalkan variasi nama/kode legacy agar hasil tetap konsisten (contoh: `Annual Leave` dan `annual_leave` dianggap ekuivalen).

RBAC:
- HCM Admin: melihat semua
- Non-admin: hanya data milik sendiri (scope otomatis)

Success `200`:
- `data[]` — halaman hasil
- `meta.pagination`: `page`, `perPage`, `total`, `totalPages`
- `meta.summary`:
  - admin list semua: ringkasan global
  - scope `me` / non-admin: ringkasan milik sendiri
- `meta.balanceSummary` (scope `me` / non-admin): ringkasan saldo cuti foundation per tipe (`year`, `totalBalance`, `totalUsed`, `byType[]`)
- `meta.holidays[]`: referensi holiday yang dipakai perhitungan workday leave, termasuk `holidayId` jika linked ke tabel `holidays`
- `meta.filters`: echo filter aktif dari request

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "userId": 10,
      "employeeName": "Budi",
      "email": "budi@company.com",
      "leaveType": "sick_leave",
      "leaveTypeLabel": "Sick Leave",
      "dateFrom": "2026-04-01",
      "dateTo": "2026-04-02",
      "days": 2,
      "status": "pending",
      "notes": ""
    }
  ],
  "meta": {
    "pagination": { "page": 1, "perPage": 20, "total": 1, "totalPages": 1 },
    "summary": { "totalRequests": 1, "pending": 1, "approved": 0, "declined": 0 }
  }
}
```

### GET `/leave-requests/export`

Export CSV server-side (tanpa paging di frontend) dengan filter yang sama seperti list.

Query:
- `scope`, `leaveType`, `status`, `dateFrom`, `dateTo`, `userId`

RBAC:
- Admin bisa export semua atau per user (`userId`)
- Non-admin otomatis hanya data sendiri

Success `200`:
- `Content-Type: text/csv; charset=UTF-8`
- `Content-Disposition: attachment; filename="leave-requests-*.csv"`

### POST `/leave-requests`

Body:
- `userId` (optional, admin only) integer, exists `users.id`
- `leaveType` required string max 100
- `dateFrom` required date
- `dateTo` required date, `>= dateFrom`
- `days` optional numeric min 0.5 max 365 (jika kosong akan dihitung server)
- `notes` optional string max 2000

RBAC:
- Admin boleh buat untuk orang lain via `userId`
- Non-admin **tidak boleh** mengirim `userId` (403 `AUTH_FORBIDDEN`)

Success `201`:

```json
{ "success": true, "data": { "id": 1 } }
```

### PUT `/leave-requests/{id}`

RBAC & aturan:
- Owner (employee/self):
  - hanya boleh edit jika status masih `pending`
  - field editable: `leaveType`, `dateFrom`, `dateTo`, `days`, `notes`
  - jika status bukan `pending` → `422 LEAVE_NOT_EDITABLE`
- Admin update request orang lain:
  - body **wajib** `status` in `pending|approved|declined`
  - `notes` optional

Success `200`:

```json
{ "success": true }
```

Behavior note (implementation detail, non-breaking):
- Saat admin mengubah status request milik user lain:
  - transisi ke `approved` akan sinkronkan transaksi `usage` ke leave ledger foundation,
  - transisi dari `approved` ke status lain akan sinkronkan `reversal` ke ledger.
- Mekanisme ini idempotent-by-reference untuk mencegah double posting saat status berubah berulang.

### DELETE `/leave-requests/{id}`

RBAC & aturan:
- Owner saja yang boleh delete; delete request orang lain → `403 FORBIDDEN`
- Hanya `pending` yang boleh dihapus → `422 LEAVE_NOT_DELETABLE`

Success `200`:

```json
{ "success": true }
```

## Leave settings (admin-only)

### GET `/leave-settings`

RBAC:
- HCM Admin only

Success `200` (ringkas):
- `data.types[]`: konfigurasi per leave type
- `data.customPoliciesByType`: mapping leaveTypeCode → list policy

### PUT `/leave-settings/types/{code}`

Path:
- `code`: regex `[a-z_]+`

Body (semua optional):
- `isEnabled` boolean
- `days` numeric 0..366
- `carryForward` boolean
- `maxCarryDays` integer 0..366
- `earnedLeave` boolean

Success `200`:

```json
{ "success": true, "data": { "code": "sick_leave", "isEnabled": true } }
```

### POST `/leave-settings/custom-policies`

Body:
- `leaveTypeCode` optional string max 64 (pilih existing)
- `leaveTypeName` optional string max 150 (buat leave type baru jika `leaveTypeCode` kosong)
- `name` required string max 200
- `days` required numeric 0.5..366
- `assigneeUserIds` optional array; tiap item integer exists `users.id`

Success `201`:

```json
{ "success": true, "data": { "id": 1 } }
```

### PUT `/leave-settings/custom-policies/{id}`

Body:
- `name` optional string max 200
- `days` optional numeric 0.5..366
- `assigneeUserIds` optional array of `users.id`

### DELETE `/leave-settings/custom-policies/{id}`

Success `200`:

```json
{ "success": true }
```

