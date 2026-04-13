# Company Service API

Sumber kebenaran: `backend/routes/api.php` + `backend/app/Http/Controllers/Api/CompanyController.php`.

## Base path

`/v1/company` (CRUD) | `/v1/hcm/company` (tenant-specific)

Auth middleware: semua endpoint wajib `api.token`. Endpoint CRUD juga validasi admin status.

## Endpoints

### GET `/` (index)

List all companies (admin can see all, others see only their joined companies).

Auth:
- required (middleware `api.token`)
- Admin check: only admins see all companies; non-admins see only companies they're members of

Query params:
- `page` integer (default: 1) — pagination page
- `per_page` integer (default: 10) — items per page  
- `status` string (optional) — filter by status (`active`, `inactive`)

Success `200`:

```json
{
  "success": true,
  "data": {
    "companies": [
      {
        "id": 1,
        "code": "company_1",
        "name": "Company One",
        "legal_name": "Legal Name",
        "status": "active",
        "timezone": "UTC",
        "currency": "IDR",
        "country_code": "ID",
        "owner_user_id": 1,
        "owner": {
          "id": 1,
          "name": "Owner Name",
          "email": "owner@example.com"
        },
        "created_at": "2026-04-01T10:00:00Z",
        "updated_at": "2026-04-01T10:00:00Z"
      }
    ],
    "pagination": {
      "total": 10,
      "per_page": 10,
      "page": 1,
      "last_page": 1
    }
  }
}
```

Errors:
- `401 AUTH_UNAUTHORIZED` — user tidak authenticated

### POST `/` (store)

Create a new company (admin only).

Auth:
- required (middleware `api.token`)
- Admin only

Request body:

```json
{
  "code": "new_company",
  "name": "New Company",
  "legal_name": "New Company Inc",
  "status": "active",
  "timezone": "UTC",
  "currency": "IDR",
  "country_code": "ID"
}
```

Fields:
- `code` string required — unique company identifier (max 100)
- `name` string required — company name (max 255)
- `legal_name` string optional — legal company name (max 255)
- `status` string required — `active` or `inactive`
- `timezone` string required — timezone identifier (max 100)
- `currency` string required — currency code (max 10)
- `country_code` string required — country code (max 10)

Success `201`:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "code": "new_company",
    "name": "New Company",
    ...
  }
}
```

Errors:
- `403 FORBIDDEN` — user bukan admin
- `422 VALIDATION_ERROR` — validation failed (e.g., code already exists)
- `401 AUTH_UNAUTHORIZED` — not authenticated

### PUT `/{id}` (update)

Update an existing company (admin or company owner).

Auth:
- required (middleware `api.token`)
- Admin OR company owner can edit

Request body (semua field optional):

```json
{
  "name": "Updated Name",
  "status": "inactive",
  "timezone": "Asia/Jakarta"
}
```

Success `200`:

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Updated Name",
    ...
  }
}
```

Errors:
- `403 FORBIDDEN` — user tidak punya akses ke company ini
- `404 NOT_FOUND` — company tidak ditemukan
- `422 VALIDATION_ERROR` — validation failed
- `401 AUTH_UNAUTHORIZED` — not authenticated

### DELETE `/{id}` (destroy)

Delete a company (admin only).

Auth:
- required (middleware `api.token`)
- Admin only

Success `200`:

```json
{
  "success": true,
  "message": "Company deleted successfully."
}
```

Errors:
- `403 FORBIDDEN` — user bukan admin
- `404 NOT_FOUND` — company tidak ditemukan
- `401 AUTH_UNAUTHORIZED` — not authenticated

### GET `/active` (HCM-specific)

Fetch active company dari request context (tenant-aware).

Base path: `/v1/hcm/company`

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

Errors:
- `403 TENANT_REQUIRED` — tidak ada active company dari request context
- `401 AUTH_UNAUTHORIZED` — user tidak authenticated

## Implementation Notes

### Admin Check

Method `User::isHcmAdmin()` digunakan untuk validasi admin:
- Email exact match: `qa.login@example.com` → admin
- Atau designation/team mengandung keywords: admin, hr, lead, supervisor, head, owner

### Tenant Context

Endpoint `/v1/hcm/company/active` menggunakan tenant middleware untuk mendapatkan activeCompany dari request context, yang ditetapkan via:
- Header `X-Company-Code` atau `X-Company-Id` (jika disediakan)
- Atau default company dari user login context

### Frontend Integration

Data list/CRUD dapat digunakan di halaman `/companies` menggunakan module:
- `frontend/resources/js/companies-management.js` — AJAX client untuk CRUD operations
- `backend/resources/views/companies.blade.php` — template dengan modals untuk add/edit/delete

