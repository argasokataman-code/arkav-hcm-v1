# Shift & Schedule API (Phase 1)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/HcmShiftController.php` + (schedule timing) `backend/app/Http/Controllers/Api/AttendanceController.php`.

## Base path

`/v1/hcm`

Tenant context:
- Endpoint shift/schedule membaca `activeCompany` dari middleware tenant context.
- Header opsional untuk override company aktif: `X-Company-Id` atau `X-Company-Code`.
- Jika company yang dipilih bukan membership aktif user, API mengembalikan `403 TENANT_FORBIDDEN`.
- **Global Super Admin bypass:** user dengan `users.is_super_admin = 1` melewati `company_id` scoping (lihat `applyTenantScope`) dan dapat mengakses shift/schedule lintas tenant tanpa membership eksplisit.

## Shifts (Master)

- `GET /shifts` (HCM Admin)
- `POST /shifts` (HCM Admin)
- `PUT /shifts/{id}` (HCM Admin)
- `DELETE /shifts/{id}` (HCM Admin)

Catatan validasi penting:
- `code`: pola `a-z0-9_-` (lihat spec regex shared / implementasi controller)
- `startTime` / `endTime`: format `H:i`, `endTime > startTime`

### GET `/shifts`

RBAC:
- HCM Admin only

Success `200`:
- `data[]` item menyertakan `slotLabel`

### POST `/shifts`

RBAC:
- HCM Admin only

Body:
- `name` required string max 200
- `code` optional string max 64 regex `^[a-z0-9_-]+$` (unique)
- `startTime` required `H:i`
- `endTime` required `H:i`, must be `> startTime` (else 422 `VALIDATION_ERROR`)
- `description` optional string max 500
- `isActive` optional boolean
- `sortOrder` optional int 0..65535

Success `201`: `{ success: true, data: { id } }`

### PUT `/shifts/{id}`

RBAC:
- HCM Admin only

Body: sama seperti POST, dengan unique check ignore current id.

Not found `404`:
```json
{
	"success": false,
	"error": {
		"code": "SHIFT_NOT_FOUND",
		"message": "Shift not found."
	}
}
```

### DELETE `/shifts/{id}`

RBAC:
- HCM Admin only

Not found `404`:
```json
{
	"success": false,
	"error": {
		"code": "SHIFT_NOT_FOUND",
		"message": "Shift not found."
	}
}
```

## Schedule timing per user

- `GET /schedule-timing` (HCM Admin)
- `PUT /schedule-timing/{userId}` (HCM Admin)
- `DELETE /schedule-timing/{userId}` (HCM Admin)

Catatan:
- `shiftId` optional; jika diisi, jam kerja mengikuti master shift
- Jika tanpa `shiftId`, `startTime` dan `endTime` wajib (`required_without`)
- Kontrak aktif memakai numeric identifier: `shiftId` mengacu ke `hcm_shifts.id`, dan `{userId}` mengacu ke `users.id`.
- Write endpoint schedule timing hanya boleh memodifikasi target user yang masih menjadi membership aktif di company context yang sedang dipilih; selain itu API mengembalikan `404 USER_NOT_IN_COMPANY`.

Lihat detail kontrak schedule timing di `docs/api/hcm-attendance-api.md` bagian **Schedule timing (admin)** (source of truth tetap controller).

