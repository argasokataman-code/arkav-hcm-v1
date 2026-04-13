# Company Service API

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/CompanyController.php`.

## Base path

`/v1/company`

Auth middleware: semua endpoint dalam `company` service wajib `api.token` + `tenant.context`.

## Endpoints

### GET `/active`

Fetch active company dari request context (ditentukan via header `X-Company-Code` atau `X-Company-Id` atau default dari login).

Auth:
- required (middleware `api.token`, `tenant.context`)
- Tenant context dari middleware (activeCompany)

Success `200`:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "code": "default_company",
    "name": "Default Company",
    "legalName": "Default Company",
    "status": "active",
    "timezone": "UTC",
    "currency": "IDR",
    "countryCode": "ID",
    "owner": {
      "id": 1,
      "name": "Owner Name",
      "email": "owner@example.com"
    },
    "memberCount": 5,
    "currentUserRole": "owner",
    "currentUserJoinedAt": "2026-04-01T10:00:00Z",
    "subscription": {
      "id": 1,
      "planCode": "starter",
      "status": "active",
      "startsAt": "2026-04-01T00:00:00Z",
      "endsAt": "2026-05-01T00:00:00Z",
      "trialEndsAt": null,
      "autoRenew": true
    },
    "createdAt": "2026-04-01T10:00:00Z",
    "updatedAt": "2026-04-01T10:00:00Z"
  }
}
```

Response fields:
- `id` integer — ID perusahaan
- `code` string — unique company code (untuk login as company)
- `name` string — nama tampilan perusahaan
- `legalName` string — nama hukum perusahaan (opsional)
- `status` string — status (active, inactive, etc)
- `timezone` string — zona waktu default
- `currency` string — kode mata uang (IDR, USD, etc)
- `countryCode` string — kode negara (ID, SG, etc)
- `owner` object — info pemilik perusahaan (id, name, email)
- `memberCount` integer — jumlah anggota aktif
- `currentUserRole` string — role user di perusahaan ini (owner, admin, member)
- `currentUserJoinedAt` string ISO8601 — tanggal user bergabung
- `subscription` object — info langganan perusahaan (planCode, status, dates, autoRenew) — nullable jika tidak ada subscription aktif
- `createdAt` string ISO8601 — tanggal pembuatan perusahaan
- `updatedAt` string ISO8601 — tanggal update terakhir

Errors:
- `403 TENANT_REQUIRED` — tidak ada active company dari request context
- `401 AUTH_UNAUTHORIZED` — user tidak authenticated

## Catatan implementasi

- Endpoint ini selalu menggunakan tenant context dari middleware, tidak ada parameter route/query.
- Jika user request company yang berbeda (via X-Company-Id atau X-Company-Code), tenant middleware akan memvalidasi membership.
- Response `activeCompany` dan membership info dari perspective user yang login.
- Dengan data ini, FE bisa display company card/widget di dashboard/profile area.
