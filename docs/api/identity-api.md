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

Success `200`:
- set cookie `arcav_access_token` (HttpOnly) + juga return `accessToken` untuk client yang butuh header

```json
{
  "success": true,
  "data": {
    "accessToken": "…",
    "tokenType": "Bearer",
    "expiresIn": 3600,
    "user": { "id": 1, "name": "Budi", "email": "budi@company.com", "roles": ["employee"] }
  }
}
```

Errors:
- `401 AUTH_INVALID_CREDENTIALS`
- `422 VALIDATION_ERROR`
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
    "roles": ["employee"],
    "hcmAdmin": false,
    "activeCompany": {
      "id": 1,
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

## Regex / validation parity

Regex shared ada di `docs/api/api-spec-phase-1.md` bagian **2) Regex patterns (shared)** dan **wajib parity FE/BE** untuk input form.

