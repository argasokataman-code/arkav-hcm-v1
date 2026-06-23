# Leave API (Phase 1)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/HcmLeaveRequestController.php` + `backend/app/Http/Controllers/Api/HcmLeaveSettingController.php`.

## Base path

`/v1/hcm`

## Tenant context

- `leave_requests.company_id` — kolom tenant ditambahkan via migrasi; backfill ke `default_company`.
- Semua query `LeaveRequest` di-scope ketat ke `company_id` aktif (`WHERE company_id = ?`).
- **Global Super Admin bypass:** user dengan `users.is_super_admin = 1` melewati scope `company_id` di `applyTenantScope` dan melihat leave request lintas tenant.
- `GET /leave-requests`, `POST /leave-requests`, `PUT /leave-requests/:id`, `DELETE /leave-requests/:id`, `GET /leave-requests/export` — semua mem-filter by active company.
- Admin dari company A tidak dapat approve/decline leave request milik company B.
- Admin juga tidak dapat create/filter/balance-check untuk `userId` yang bukan anggota tenant aktif.
- Header opsional: `X-Company-Id` / `X-Company-Code`; jika company bukan milik user → `403 TENANT_FORBIDDEN`.

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

## Leave type catalog (admin-only)

Sumber data: `hcm_leave_type_settings`

> **Multi-tenant isolation:** Semua query di-scope ke `company_id` aktif dari header `X-Company-Id`. Setiap tenant hanya melihat dan mengelola leave type milik perusahaannya sendiri.

### GET `/leave-types`

RBAC:
- HCM Admin only (permission: `leave.view`)
- Scoped ke active company (`WHERE company_id = ?`)

Success `200`:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "sick_leave",
      "name": "Sick Leave",
      "isEnabled": true,
      "days": 5,
      "carryForward": false,
      "maxCarryDays": null,
      "earnedLeave": false,
      "sortOrder": 1
    }
  ]
}
```

### POST `/leave-types`

Body:
- `code` required string max 64, regex `[a-z0-9_]+`, unique per company
- `name` required string max 150
- `days` optional numeric 0..366
- `carryForward` optional boolean
- `maxCarryDays` optional integer 0..366
- `earnedLeave` optional boolean
- `isEnabled` optional boolean
- `sortOrder` optional integer 0..255

Errors:
- `422 DUPLICATE_CODE` — kode sudah dipakai oleh company ini

Success `201`:

```json
{ "success": true, "data": { "id": 1 } }
```

### PUT `/leave-types/{id}`

Hanya boleh update record milik company aktif (`WHERE company_id = ? AND id = ?`). Return 404 jika ID tidak ditemukan di company ini.

Body:
- `name` required string max 150
- `days` optional numeric 0..366
- `carryForward` optional boolean
- `maxCarryDays` optional integer 0..366
- `earnedLeave` optional boolean
- `isEnabled` optional boolean
- `sortOrder` optional integer 0..255

Success `200`:

```json
{ "success": true, "data": { "id": 1 } }
```

### DELETE `/leave-types/{id}`

Soft-disable leave type by setting `is_enabled = false`. Hanya boleh akses record milik company aktif.

Success `200`:

```json
{ "success": true, "message": "Leave type disabled successfully." }
```

## Leave requests

Resource: `/leave-requests`

Status:
- `pending` (default)
- `approved`
- `declined`
- `cancelled` (via self-cancel endpoint)

### GET `/leave-requests`

Query:
- `scope`: `me` (optional)
- `page` optional int ≥1
- `perPage` optional int 1..100 (default 20)
- `leaveType` optional string
- `status` optional `pending|approved|declined`
- `dateFrom` optional date
- `dateTo` optional date
- `userId` optional integer `users.id` (admin only). UUID masih diterima sebagai fallback legacy, tetapi runtime UI aktif mengirim numeric id.

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

Export tabular server-side (tanpa paging di frontend) dengan filter yang sama seperti list.

Query:
- `scope`, `leaveType`, `status`, `dateFrom`, `dateTo`, `userId`
- `format` — `xlsx` (default) | `csv`

RBAC:
- Admin bisa export semua atau per user (`userId`)
- Non-admin otomatis hanya data sendiri

Success `200`:
- `Content-Type`: 
  - `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` (default)
  - `text/csv; charset=UTF-8` jika `format=csv`
- `Content-Disposition: attachment; filename="leave-requests-*.<xlsx|csv>"`

### GET `/employee-leave-balance`

Query:
- `leaveType` required string max 100
- `userId` optional integer `users.id` (admin only, same-tenant only). UUID masih diterima sebagai fallback legacy.

RBAC:
- Employee: bisa melihat saldo dirinya sendiri.
- Admin: bisa melihat saldo user lain hanya jika user tersebut anggota tenant aktif.

Error penting:
- `404 USER_NOT_IN_COMPANY` jika `userId` bukan anggota tenant aktif.
- `403 FORBIDDEN` jika non-admin mencoba membaca saldo user lain.

### POST `/leave-requests`

Body:
- `userId` (optional, admin only) integer, exists `users.id`, dan wajib anggota tenant aktif. UUID masih diterima sebagai fallback legacy.
- `leaveType` required string max 100
- `dateFrom` required date
- `dateTo` required date, `>= dateFrom`
- `days` optional numeric min 0.5 max 365 (jika kosong akan dihitung server)
- `notes` optional string max 2000

RBAC:
- Admin boleh buat untuk orang lain via `userId` jika target user anggota tenant aktif
- Non-admin **tidak boleh** mengirim `userId` (403 `AUTH_FORBIDDEN`)

Error codes:
- `422 LEAVE_DATE_OVERLAP` — rentang tanggal tumpang tindih dengan pengajuan cuti pending/approved lain
- `422 LEAVE_OT_CONFLICT` — ada approved overtime pada rentang tanggal yang sama
- `422 LEAVE_NO_WORKING_DAY` — rentang tanggal tidak memiliki hari kerja
- `422 LEAVE_INSUFFICIENT_BALANCE` — saldo cuti tidak mencukupi
- `422 LEAVE_EXCEEDS_MAX_CONSECUTIVE` — melebihi batas maksimal hari berturut-turut menurut kebijakan cuti

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
  - jika mengubah `dateFrom`/`dateTo` dan tumpang tindih dengan leave lain → `422 LEAVE_DATE_OVERLAP`
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

### POST `/leave-requests/{id}/cancel`

Batalkan pengajuan cuti oleh pemilik (employee/self). Bisa membatalkan status `pending` atau `approved`.

RBAC & aturan:
- Owner saja yang boleh cancel; cancel milik orang lain → `403 FORBIDDEN`
- Status yang bisa di-cancel: `pending`, `approved`
- Status lain → `422 LEAVE_NOT_CANCELLABLE`
- Jika cancel dari `approved`:
  - Ledger otomatis reversal (saldo dikembalikan)
  - Attendance `on_leave` diubah jadi `absent`
  - Notifikasi `LeaveCancelledNotification` dikirim

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

