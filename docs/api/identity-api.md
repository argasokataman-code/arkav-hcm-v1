# Identity Service API (Phase 1)

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/AuthController.php`.

## Base path

`/v1/identity`

## Auth

### POST `/auth/register`

Body:
- `name` required string min 2 max 150, regex `name` (`/^[A-Za-z][A-Za-z\s'.-]{1,149}$/`)
- `email` required string email:rfc max 255 unique `users.email`
- `password` required string regex `password_strong`
- `confirmPassword` required same:password

Success `201`:

```json
{
  "success": true,
  "data": {
    "user": { "id": 1, "name": "Budi", "email": "budi@company.com", "status": "active" }
  }
}
```

Behavior note (tenant foundation):
- Setelah register sukses, backend otomatis memastikan user tergabung ke `default_company` pada tabel `company_users` dengan status `active`.
- Ini menjaga kompatibilitas single-company deployment sambil menyiapkan migrasi ke tenant context.

Errors:
- `422 VALIDATION_ERROR` (format mengikuti error envelope)

### POST `/auth/login`

Throttle:
- max 5 attempts / key `(email|ip)`; jika exceed → `429 AUTH_TOO_MANY_ATTEMPTS` + `retryAfterSeconds`

Body:
- `email` required string email:rfc
- `password` required string min 8 max 64
- `rememberMe` optional boolean (ttl lebih lama)
- `companyCode` optional string regex `^[A-Za-z0-9_-]+$` (mode "login as company")

Success `200`:
- set cookie `arcav_access_token` (HttpOnly) + juga return `accessToken` untuk client yang butuh header

```json
{
  "success": true,
  "data": {
    "accessToken": "…",
    "tokenType": "Bearer",
    "expiresIn": 3600,
    "user": { "id": 1, "name": "Budi", "email": "budi@company.com", "roles": ["employee"] },
    "activeCompany": {
      "id": 1,
      "uuid": "11111111-2222-3333-4444-555555555555",
      "code": "default_company",
      "name": "Default Company",
      "role": "member"
    }
  }
}
```

Behavior note (tenant login mode):
- Jika `companyCode` diisi, backend memverifikasi membership aktif user ke company tersebut saat login.
- Jika user adalah owner/admin tenant dan login tanpa `companyCode`, backend menolak dengan `422 AUTH_COMPANY_MODE_REQUIRED`.
- Jika tidak punya akses ke company yang diminta, login ditolak dengan `403 TENANT_FORBIDDEN`.

Errors:
- `401 AUTH_INVALID_CREDENTIALS`
- `403 TENANT_FORBIDDEN`
- `422 VALIDATION_ERROR`
- `422 AUTH_COMPANY_MODE_REQUIRED`
- `429 AUTH_TOO_MANY_ATTEMPTS`

### POST `/auth/logout` (protected)

Auth:
- required (middleware `api.token`)

Behavior:
- revoke token (set `revoked_at`) jika ada
- clear cookie token

Success `200`:

```json
{ "success": true, "data": { "message": "Logged out successfully" } }
```

### GET `/auth/me` (protected)

Auth:
- required

Tenant context headers (opsional):
- `X-Company-Id`: pilih company aktif berdasarkan id membership user
- `X-Company-Code`: alternatif pemilihan berdasarkan kode company

Jika header memilih company yang bukan membership aktif user, endpoint tenant-aware akan mengembalikan `403 TENANT_FORBIDDEN`.

Success `200`:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Budi",
    "email": "budi@company.com",
    "profile": {
      "firstName": "Budi",
      "lastName": "Santoso",
      "phone": "08123456789",
      "address": "Jl. Merdeka 1",
      "addressDetail": "Jakarta",
      "designation": "Staff",
      "team": "HR",
      "profilePhotoUrl": "/storage/profile-photos/1.jpg"
    },
    "roles": ["employee"],
    "hcmAdmin": false,
    "hcmGlobalAdmin": false,
    "permissions": {
      "training.view": true
    },
    "permissionCodes": ["training.view"],
    "activeCompany": {
      "id": 1,
      "uuid": "11111111-2222-3333-4444-555555555555",
      "code": "default_company",
      "name": "Default Company",
      "role": "member"
    }
  }
}
```

Errors:
- `401 AUTH_UNAUTHORIZED`
- `403 TENANT_MEMBERSHIP_REQUIRED`
- `403 TENANT_FORBIDDEN`

Frontend hardening note:
- Login form menyimpan tenant context hanya dari `activeCompany` hasil backend.
- Jika payload sukses login company tidak menyertakan tenant aktif yang valid, FE membatalkan redirect dan menampilkan error.

### PUT `/auth/profile` (protected)

Auth:
- required (middleware `api.token` + `tenant.context`)

Body:
- `name` required string min 2 max 150
- `email` required string email:rfc max 255 unique `users.email` (ignore current user)
- `phone` optional nullable string max 50
- `address` optional nullable string max 500
- `addressDetail` optional nullable string max 500
- `currentPassword` optional string max 64 (wajib jika ubah password)
- `newPassword` optional string regex `password_strong`
- `confirmPassword` required_with:newPassword same:newPassword

Success `200`:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Budi Santoso",
    "email": "budi.santoso@company.com",
    "profile": {
      "firstName": "Budi",
      "lastName": "Santoso",
      "phone": "08123456789",
      "address": "Jl. Merdeka 1",
      "addressDetail": "Jakarta",
      "designation": "Staff",
      "team": "HR",
      "profilePhotoUrl": "/storage/profile-photos/1.jpg"
    }
  }
}
```

Errors:
- `401 AUTH_UNAUTHORIZED`
- `403 TENANT_MEMBERSHIP_REQUIRED`
- `403 TENANT_FORBIDDEN`
- `422 VALIDATION_ERROR`
- `422 AUTH_INVALID_CREDENTIALS` (saat `currentPassword` tidak valid ketika ubah password)

## Regex / validation parity

Regex shared ada di `docs/api/api-spec-phase-1.md` bagian **2) Regex patterns (shared)** dan **wajib parity FE/BE** untuk input form.

